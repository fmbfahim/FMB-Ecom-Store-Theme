(function($) {
    'use strict';

    const AdsDeviceFingerprint = {
        init: function() {
            // Guarantee zero-latency checkout load by deferring execution
            const runFingerprint = () => {
                this.generateAndInjectFingerprint();
            };

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(runFingerprint, { timeout: 2000 });
            } else {
                setTimeout(runFingerprint, 500);
            }
        },

        generateAndInjectFingerprint: async function() {
            try {
                let deviceHash = this.getStoredHash();
                let hardwareHash = this.getStoredHardwareHash();

                if (!deviceHash || !hardwareHash) {
                    const fullPrint = await this.getFullFingerprint();
                    const hardwarePrint = await this.getHardwareOnlyFingerprint();
                    
                    deviceHash = await this.hashStringSHA256(fullPrint);
                    hardwareHash = await this.hashStringSHA256(hardwarePrint);
                    
                    this.storeHash(deviceHash);
                    this.storeHardwareHash(hardwareHash);
                }

                this.injectIntoCheckoutForm(deviceHash, hardwareHash);
            } catch (error) {
                console.error('ADS Fingerprint Error: ', error);
            }
        },

        getWebGLFingerprint: function() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return 'no-webgl';
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (!debugInfo) return 'no-debug-info';
                
                const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                if (!renderer) return 'no-renderer';
                
                // Chrome outputs: ANGLE (Vendor, GPU_Name Direct3D... )
                // Firefox outputs: GPU_Name Direct3D...
                // We aggressively clean strings to match true GPU hardware name across all browsers
                let cleanGpu = renderer.replace(/angle|direct3d|opengl|vs_[0-9_]+|ps_[0-9_]+|d3d[0-9]+/gi, '')
                                     .replace(/[()]/g, '')
                                     .replace(/\s+/g, ' ')
                                     .trim()
                                     .toLowerCase();
                                     
                // Isolate the core GPU architecture if possible
                const coreGpuMatch = cleanGpu.match(/(nvidia|amd|intel|apple|mali|adreno|powervr|qualcomm)\s+([^,]+)/i);
                if (coreGpuMatch) {
                    cleanGpu = coreGpuMatch[0].trim();
                }
                
                return cleanGpu;
            } catch (e) {
                return 'webgl-error';
            }
        },

        getCanvasFingerprint: function() {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 50;
                const ctx = canvas.getContext('2d');
                if (!ctx) return 'no-canvas';

                // Complex geometry for subpixel driver deviations
                ctx.textBaseline = "top";
                ctx.font = "14px 'Arial'";
                ctx.textBaseline = "alphabetic";
                ctx.fillStyle = "#f60";
                ctx.fillRect(125,1,62,20);
                
                ctx.fillStyle = "#069";
                ctx.fillText("FMB Engine \uD83D\uDE00", 2, 15);
                ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
                ctx.fillText("FMB Engine \uD83D\uDE00", 4, 17);
                
                return canvas.toDataURL();
            } catch (e) {
                return 'canvas-error';
            }
        },

        getAudioFingerprint: async function() {
            return new Promise((resolve) => {
                try {
                    const audioCtx = new (window.OfflineAudioContext || window.webkitOfflineAudioContext)(1, 44100, 44100);
                    const oscillator = audioCtx.createOscillator();
                    const dynamicsCompressor = audioCtx.createDynamicsCompressor();
                    
                    oscillator.type = 'triangle';
                    oscillator.frequency.value = 10000;
                    
                    dynamicsCompressor.threshold.value = -50;
                    dynamicsCompressor.knee.value = 40;
                    dynamicsCompressor.ratio.value = 12;
                    dynamicsCompressor.attack.value = 0;
                    dynamicsCompressor.release.value = 0.25;

                    oscillator.connect(dynamicsCompressor);
                    dynamicsCompressor.connect(audioCtx.destination);
                    oscillator.start(0);

                    audioCtx.oncomplete = function(event) {
                        try {
                            const samples = event.renderedBuffer.getChannelData(0);
                            let hash = 0;
                            for (let i = 0; i < samples.length; ++i) {
                                hash += Math.abs(samples[i]);
                            }
                            resolve(hash.toString());
                        } catch (e) {
                            resolve('audio-processing-error');
                        }
                    };
                    audioCtx.startRendering();
                } catch (e) {
                    resolve('no-audio-context');
                }
            });
        },

        getFullFingerprint: async function() {
            const screenW = window.screen.width || 0;
            const screenH = window.screen.height || 0;
            const trueW = Math.max(screenW, screenH);
            const trueH = Math.min(screenW, screenH);
            
            const audioFP = await this.getAudioFingerprint();
            const canvasFP = this.getCanvasFingerprint();

            const data = [
                trueW + 'x' + trueH,
                window.screen.colorDepth || 24,
                navigator.hardwareConcurrency || 2, // Hardcast logical CPU cores
                navigator.deviceMemory || 4, // Hardcast exact RAM allocation bracket
                new Date().getTimezoneOffset(), // Fixed system Timezone offset
                (navigator.language || 'en').split('-')[0].toLowerCase(), // Base OS language
                this.getWebGLFingerprint(), // Hardcast GPU silicon identity
                audioFP, // Hardware Audio Subprocessing
                canvasFP, // Graphics Subpixel Engine
                navigator.platform || 'unknown', // Underlying OS Platform (Win32, MacIntel, etc)
                navigator.maxTouchPoints || 0, // Hardware Touch capability
                (window.Intl && window.Intl.DateTimeFormat) ? window.Intl.DateTimeFormat().resolvedOptions().timeZone : 'unknown', // True String Timezone (e.g. Asia/Dhaka)
                navigator.vendor || 'unknown', // Browser Vendor engine
                navigator.doNotTrack || 'unknown' // User Privacy preference
            ];
            return data.join('||');
        },

        getHardwareOnlyFingerprint: async function() {
            // Strictly hardware identity - EXCLUDING all browser rendering (Canvas/Audio/Vendor) to allow cross-browser tracking
            const screenW = window.screen.width || 0;
            const screenH = window.screen.height || 0;
            const trueW = Math.max(screenW, screenH);
            const trueH = Math.min(screenW, screenH);

            const data = [
                trueW + 'x' + trueH,
                navigator.hardwareConcurrency || 2, // Hardcast logical CPU cores
                navigator.deviceMemory || 4, // Hardcast exact RAM allocation bracket
                new Date().getTimezoneOffset(), // Fixed system Timezone offset
                this.getWebGLFingerprint(), // Core GPU identifier (cleaned)
                navigator.platform || 'unknown', // OS layer
                navigator.maxTouchPoints || 0, // Touch capability
                (window.Intl && window.Intl.DateTimeFormat) ? window.Intl.DateTimeFormat().resolvedOptions().timeZone : 'unknown' // String Timezone
            ];
            return data.join('||');
        },

        hashStringSHA256: async function(str) {
            const utf8 = new TextEncoder().encode(str);
            const hashBuffer = await crypto.subtle.digest('SHA-256', utf8);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex;
        },

        getStoredHash: function() {
            let hash = null;
            try { hash = localStorage.getItem('_ads_did'); } catch (e) {}
            if (!hash) {
                const match = document.cookie.match(new RegExp('(^| )_ads_did=([^;]+)'));
                if (match) hash = match[2];
            }
            return hash;
        },

        getStoredHardwareHash: function() {
            let hash = null;
            try { hash = localStorage.getItem('_ads_hw_did'); } catch (e) {}
            if (!hash) {
                const match = document.cookie.match(new RegExp('(^| )_ads_hw_did=([^;]+)'));
                if (match) hash = match[2];
            }
            return hash;
        },

        storeHash: function(hash) {
            try { localStorage.setItem('_ads_did', hash); } catch (e) {}
            try {
                const d = new Date(); d.setTime(d.getTime() + (365 * 24 * 60 * 60 * 1000));
                document.cookie = "_ads_did=" + hash + ";expires=" + d.toUTCString() + ";path=/;SameSite=None;Secure";
            } catch (e) {}
        },

        storeHardwareHash: function(hash) {
            try { localStorage.setItem('_ads_hw_did', hash); } catch (e) {}
            try {
                const d = new Date(); d.setTime(d.getTime() + (365 * 24 * 60 * 60 * 1000));
                document.cookie = "_ads_hw_did=" + hash + ";expires=" + d.toUTCString() + ";path=/;SameSite=None;Secure";
            } catch (e) {}
        },

        injectIntoCheckoutForm: function(deviceHash, hardwareHash) {
            $(document).ready(function() {
                // Wait for form to exist if it's rendered late dynamically
                const inject = function() {
                    const $form = $('form.checkout, form#order_review');
                    if ($form.length > 0) {
                        if ($form.find('input[name="ads_device_hash"]').length === 0) {
                            $form.append('<input type="hidden" name="ads_device_hash" id="ads_device_hash" value="' + deviceHash + '">');
                        } else {
                            $form.find('input[name="ads_device_hash"]').val(deviceHash);
                        }

                        if ($form.find('input[name="ads_hardware_hash"]').length === 0) {
                            $form.append('<input type="hidden" name="ads_hardware_hash" id="ads_hardware_hash" value="' + hardwareHash + '">');
                        } else {
                            $form.find('input[name="ads_hardware_hash"]').val(hardwareHash);
                        }
                    }
                };

                inject();
                
                // Hook into standard WooCommerce trigger just in case the form is updated
                $(document.body).on('updated_checkout init_checkout', function() {
                    inject();
                });
            });
        }
    };

    // Initialize
    AdsDeviceFingerprint.init();

})(jQuery);
