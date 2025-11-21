<?php
/**
 * Добавляем страницу в админку
 */
add_action('admin_menu', function() {
    add_menu_page(
        'Zoom Meetings',
        'Zoom Meetings',
        'manage_options',
        'zoom-meetings',
        'zoom_render_admin_page',
        'dashicons-video-alt3',
        30
    );
});

function zoom_render_admin_page() {
    $meetings = Zoom_OAuth::list_meetings();
    
    if (is_wp_error($meetings)) {
        echo '<div class="notice notice-error"><p>Помилка: ' . $meetings->get_error_message() . '</p></div>';
        return;
    }
    
    $meetings_list = $meetings['meetings'] ?? array();
    ?>
    <div class="wrap">
        <h1>📋 Zoom Meetings</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Тема</th>
                    <th>Дата</th>
                    <th>Тривалість</th>
                    <th>Пароль</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($meetings_list)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">Зустрічей не знайдено</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($meetings_list as $meeting): ?>
                        <tr>
                            <td><?php echo esc_html($meeting['id']); ?></td>
                            <td><strong><?php echo esc_html($meeting['topic']); ?></strong></td>
                            <td>
                                <?php 
                                if (!empty($meeting['start_time'])) {
                                    echo date('d.m.Y H:i', strtotime($meeting['start_time']));
                                } else {
                                    echo 'Миттєва';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($meeting['duration']); ?> хв</td>
                            <td><?php echo esc_html($meeting['password'] ?? '-'); ?></td>
                            <td>
                                <a href="<?php echo esc_url($meeting['join_url']); ?>" target="_blank" class="button">
                                    Приєднатись
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}