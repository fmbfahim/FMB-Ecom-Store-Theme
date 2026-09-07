<?php
/**
 * Contact Messages Admin Page
 */

if (!defined('ABSPATH')) exit;

function fmb_admin_contact_messages_page() {
    if (!current_user_can('manage_woocommerce')) {
        echo '<div class="wrap"><p>Unauthorized.</p></div>';
        return;
    }

    $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $args = array(
        'post_type'      => 'fmb_contact_msg',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'paged'          => $paged,
    );

    $query = new WP_Query($args);
    $total_pages = $query->max_num_pages;

    ?>
    <div class="fmb-admin-wrap">
        <h1 style="font-size:1.5rem;font-weight:800;color:#111827;margin-bottom:20px;">
            ✉️ Contact Messages
        </h1>

        <div class="fmb-card">
            <div class="fmb-card-header">
                <h2>All Messages</h2>
            </div>
            
            <div style="overflow-x:auto;">
                <table class="fmb-table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Date</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($query->have_posts()) : ?>
                            <?php while ($query->have_posts()) : $query->the_post(); 
                                $c_name  = get_post_meta(get_the_ID(), '_c_name', true);
                                $c_phone = get_post_meta(get_the_ID(), '_c_phone', true);
                                $subject = get_the_title();
                                $message = get_the_content();
                                $date    = get_the_date('d M Y, h:i A');
                                $id      = get_the_ID();
                            ?>
                                <tr id="msg-row-<?php echo $id; ?>">
                                    <td style="font-size:12px;color:#6b7280;"><?php echo esc_html($date); ?></td>
                                    <td style="font-weight:600;"><?php echo esc_html($c_name ? $c_name : '—'); ?></td>
                                    <td><a href="tel:<?php echo esc_attr($c_phone); ?>"><?php echo esc_html($c_phone ? $c_phone : '—'); ?></a></td>
                                    <td><?php echo esc_html($subject); ?></td>
                                    <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?php echo esc_html($message); ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="fmb-actions" style="justify-content:flex-end;">
                                            <button type="button" class="fmb-btn fmb-btn-outline fmb-btn-sm fmb-view-msg-btn" 
                                                    data-name="<?php echo esc_attr($c_name); ?>"
                                                    data-phone="<?php echo esc_attr($c_phone); ?>"
                                                    data-subject="<?php echo esc_attr($subject); ?>"
                                                    data-date="<?php echo esc_attr($date); ?>"
                                                    data-message="<?php echo esc_attr($message); ?>">
                                                View
                                            </button>
                                            <button type="button" class="fmb-btn fmb-btn-danger fmb-btn-sm fmb-delete-msg-btn" data-id="<?php echo $id; ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:30px;color:#6b7280;">No messages found.</td>
                            </tr>
                        <?php endif; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
            <div class="fmb-pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a href="<?php echo admin_url('admin.php?page=fmb-contact-messages&paged=' . $i); ?>" class="fmb-page-btn <?php echo ($i === $paged) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Modal -->
    <div class="fmb-modal-bg" id="fmb-msg-modal">
        <div class="fmb-modal" style="max-width:500px;">
            <div class="fmb-modal-header">
                <h3>Message Details</h3>
                <button type="button" class="fmb-modal-close" id="fmb-msg-close">&times;</button>
            </div>
            <div class="fmb-modal-body">
                <div style="background:#f9fafb;border-radius:10px;padding:14px;margin-bottom:16px;">
                    <p style="margin:4px 0;font-size:13px;"><strong>Date:</strong> <span id="v-date"></span></p>
                    <p style="margin:4px 0;font-size:13px;"><strong>Name:</strong> <span id="v-name"></span></p>
                    <p style="margin:4px 0;font-size:13px;"><strong>Phone:</strong> <span id="v-phone"></span></p>
                    <p style="margin:4px 0;font-size:13px;"><strong>Subject:</strong> <span id="v-subject"></span></p>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:6px;text-transform:uppercase;">Message</label>
                    <div id="v-message" style="font-size:14px;line-height:1.6;color:#374151;white-space:pre-wrap;background:#f3f4f6;padding:12px;border-radius:8px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('fmb-msg-modal');
        const btnClose = document.getElementById('fmb-msg-close');
        
        // View Action
        document.querySelectorAll('.fmb-view-msg-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('v-name').textContent = this.dataset.name || '—';
                document.getElementById('v-phone').innerHTML = this.dataset.phone ? `<a href="tel:${this.dataset.phone}">${this.dataset.phone}</a>` : '—';
                document.getElementById('v-subject').textContent = this.dataset.subject;
                document.getElementById('v-date').textContent = this.dataset.date;
                document.getElementById('v-message').textContent = this.dataset.message;
                modal.classList.add('open');
            });
        });

        // Close Modal
        btnClose.addEventListener('click', () => modal.classList.remove('open'));
        modal.addEventListener('click', (e) => {
            if(e.target === modal) modal.classList.remove('open');
        });

        // Delete Action
        document.querySelectorAll('.fmb-delete-msg-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if(!confirm('Are you sure you want to delete this message?')) return;
                const id = this.dataset.id;
                const row = document.getElementById('msg-row-' + id);
                
                // Disable button
                this.disabled = true;
                this.style.opacity = '0.5';

                const fd = new FormData();
                fd.append('action', 'fmb_delete_contact_msg');
                fd.append('msg_id', id);
                fd.append('nonce', '<?php echo wp_create_nonce("fmb_contact_msg_action"); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        row.remove();
                    } else {
                        alert(res.data || 'Failed to delete');
                        this.disabled = false;
                        this.style.opacity = '1';
                    }
                })
                .catch(err => {
                    alert('Error deleting message');
                    this.disabled = false;
                    this.style.opacity = '1';
                });
            });
        });
    });
    </script>
    <?php
}
