<?php
require_once __DIR__ . '/class-db-crud-tables.php';

class Migration {
    private $db_crud;
    private static $instance = null;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'migration_db_menu' ] );
        $this->db_crud = new DB_Insertion();
        $this->migrate_db_handle();
    }

    public static function get_instance() {
        if ( null === self::$instance ) { // ИСПРАВЛЕНО: self::$instance
            self::$instance = new self();
        }
        
        return self::$instance; // ИСПРАВЛЕНО: добавлен return
    }

    public function activate() {
        $this->db_crud->insert_tables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        $this->db_crud->delete_tables();
        flush_rewrite_rules();
    }

    public function migration_db_menu() {
        add_management_page(
            'Migration DB',
            'Migration DB',
            'manage_options',
            'migration-db',
            array( $this, 'migration_db_page' )
        );
    }

    public function migration_db_page() {
        echo '
            <style>
                .migration-wrap {
                    background: #fff;
                    padding: 25px;
                    border-radius: 10px;
                    max-width: 450px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    margin-top: 30px;
                }
                .migration-title {
                    margin-bottom: 20px;
                }
                .migration-btns {
                    display: flex;
                    gap: 10px;
                }
                .migration-message {
                    margin-top: 20px;
                    padding: 12px;
                    border-radius: 6px;
                    font-weight: 500;
                    display: none;
                }
                .migration-message.success {
                    background: #e7f7e7;
                    border-left: 4px solid #46b450;
                    color: #1a7d1a;
                }
                .migration-message.error {
                    background: #fdeaea;
                    border-left: 4px solid #dc3232;
                    color: #a00000;
                }
                .loading-spinner {
                    display: inline-block;
                    width: 14px;
                    height: 14px;
                    border: 2px solid #fff;
                    border-top: 2px solid transparent;
                    border-radius: 50%;
                    margin-left: 8px;
                    animation: spin 0.6s linear infinite;
                    vertical-align: middle;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>

            <div class="migration-wrap">
                <h1 class="migration-title">Запустити міграцію бази даних</h1>

                <div class="migration-btns">
                    <button id="start-migration" class="button button-primary">Міграція</button>
                    <button id="rollback-migration" class="button">Відкотити</button>
                </div>

                <div id="migration-message" class="migration-message"></div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const msg = document.getElementById("migration-message");

                    async function callMigration(url, button) {
                        msg.style.display = "none";
                        const originalText = button.innerHTML;
                        button.disabled = true;
                        button.innerHTML = originalText + \'<span class="loading-spinner"></span>\';

                        const response = await fetch(url, {
                            method: "GET",
                            credentials: "same-origin",
                            headers: {
                                "X-WP-Nonce": "' . wp_create_nonce('wp_rest') . '"
                            }
                        });

                        button.disabled = false;
                        button.innerHTML = originalText;

                        if (response.ok) {
                            msg.className = "migration-message success";
                            msg.innerText = "Операція виконана успішно!";
                            msg.style.display = "block";
                        } else {
                            msg.className = "migration-message error";
                            msg.innerText = "Помилка виконання!";
                            msg.style.display = "block";
                        }
                    }

                    document.getElementById("start-migration").addEventListener("click", function() {
                        callMigration("' . esc_url(home_url()) . '/wp-json/migration/v1/init", this);
                    });

                    document.getElementById("rollback-migration").addEventListener("click", function() {
                        callMigration("' . esc_url(home_url()) . '/wp-json/migration/v1/rollback", this);
                    });
                });
            </script>
        ';
    }

    public function migrate_db_handle(){
        add_action('rest_api_init', function(){
            register_rest_route('migration/v1', 'init', [
                'method' => 'GET',
                'callback' => [$this, 'activate'],
                'permission_callback' => function(){
                    return is_user_logged_in() && current_user_can('manage_options');
                }
            ]);
        });

        add_action('rest_api_init', function(){
            register_rest_route('migration/v1', 'rollback', [
                'method' => 'GET',
                'callback' => [$this, 'deactivate'],
                'permission_callback' => function(){
                    return is_user_logged_in() && current_user_can('manage_options');
                }
            ]);
        });
    }
}