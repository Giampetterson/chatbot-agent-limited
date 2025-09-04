<?php
/**
 * Plugin Name: RentrIA Chat Widget
 * Plugin URI: https://piattaformarentriFacile.it
 * Description: Widget chat assistente virtuale RentrIA specializzato in gestione rifiuti e sistema RENTRI per WordPress con supporto streaming AI real-time
 * Version: 1.0.0
 * Author: RentrIA Team
 * Author URI: https://piattaformarentriFacile.it
 * License: Proprietary
 * Text Domain: rentria-chat
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti plugin
define('RENTRIA_CHAT_VERSION', '1.0.0');
define('RENTRIA_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RENTRIA_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Classe principale del plugin
 */
class RentriaChatWidget {
    
    /**
     * Costruttore - inizializza hooks
     */
    public function __construct() {
        // Registra shortcode
        add_shortcode('rentria_chat', array($this, 'render_chat_widget'));
        
        // Registra assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Registra endpoint AJAX per il backend
        add_action('wp_ajax_rentria_chat_process', array($this, 'process_chat_request'));
        add_action('wp_ajax_nopriv_rentria_chat_process', array($this, 'process_chat_request'));
        
        // Aggiungi menu amministrazione
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Renderizza il widget chat tramite shortcode
     * 
     * @param array $atts Attributi shortcode
     * @return string HTML del widget
     */
    public function render_chat_widget($atts) {
        // Attributi shortcode con defaults
        $atts = shortcode_atts(array(
            'height' => '650',
            'width' => '100%',
            'max_width' => '600',
            'title' => 'Assistente RentrIA',
            'placeholder' => 'Scrivi un messaggio...',
            'welcome' => 'Ciao! Sono RentrIA, il tuo assistente per la gestione dei rifiuti e il sistema RENTRI. Come posso aiutarti?',
            'show_pdf' => 'true',
            'show_copy' => 'true',
            'show_whatsapp' => 'true',
            'show_telegram' => 'true',
            'theme' => 'default'
        ), $atts, 'rentria_chat');
        
        // Genera ID unico per multiple istanze
        $widget_id = 'rentria-chat-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($widget_id); ?>" class="rentria-chat-widget-container">
            <div class="rentria-chat-header">
                <h2 class="rentria-chat-title"><?php echo esc_html($atts['title']); ?></h2>
                <?php if ($atts['show_pdf'] === 'true'): ?>
                <button class="rentria-pdf-btn" id="<?php echo esc_attr($widget_id); ?>-pdf">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>PDF</span>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="rentria-chat-container" 
                 style="height: <?php echo esc_attr($atts['height']); ?>px; 
                        width: <?php echo esc_attr($atts['width']); ?>; 
                        max-width: <?php echo esc_attr($atts['max_width']); ?>px;">
                
                <div class="rentria-message-list" id="<?php echo esc_attr($widget_id); ?>-messages">
                    <?php if (!empty($atts['welcome'])): ?>
                    <div class="rentria-msg rentria-bot">
                        <div class="rentria-msg-content">
                            <?php echo esc_html($atts['welcome']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="rentria-input-area">
                    <input type="text" 
                           class="rentria-input" 
                           id="<?php echo esc_attr($widget_id); ?>-input"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>">
                    <button class="rentria-send-btn" id="<?php echo esc_attr($widget_id); ?>-send">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            // Inizializza widget con configurazione
            window.addEventListener('DOMContentLoaded', function() {
                if (typeof RentriaChatManager !== 'undefined') {
                    new RentriaChatManager('<?php echo esc_js($widget_id); ?>', {
                        showPDF: <?php echo $atts['show_pdf'] === 'true' ? 'true' : 'false'; ?>,
                        showCopy: <?php echo $atts['show_copy'] === 'true' ? 'true' : 'false'; ?>,
                        showWhatsApp: <?php echo $atts['show_whatsapp'] === 'true' ? 'true' : 'false'; ?>,
                        showTelegram: <?php echo $atts['show_telegram'] === 'true' ? 'true' : 'false'; ?>,
                        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                        nonce: '<?php echo wp_create_nonce('rentria_chat_nonce'); ?>'
                    });
                }
            });
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Registra e carica assets (CSS e JS)
     */
    public function enqueue_assets() {
        // Solo se la pagina contiene lo shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'rentria_chat')) {
            
            // Google Fonts
            wp_enqueue_style(
                'rentria-google-fonts',
                'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Oxanium:wght@300;400;500;600;700&display=swap',
                array(),
                null
            );
            
            // Plugin CSS
            wp_enqueue_style(
                'rentria-chat-styles',
                RENTRIA_CHAT_PLUGIN_URL . 'assets/rentria-chat.css',
                array(),
                RENTRIA_CHAT_VERSION
            );
            
            // Marked.js per rendering Markdown
            wp_enqueue_script(
                'marked-js',
                'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
                array(),
                null,
                true
            );
            
            // jsPDF per export PDF (caricamento condizionale)
            wp_enqueue_script(
                'jspdf',
                'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
                array(),
                '2.5.1',
                true
            );
            
            // Plugin JavaScript principale
            wp_enqueue_script(
                'rentria-chat-script',
                RENTRIA_CHAT_PLUGIN_URL . 'assets/rentria-chat.js',
                array('jquery', 'marked-js'),
                RENTRIA_CHAT_VERSION,
                true
            );
            
            // Localizza script con dati WordPress
            wp_localize_script('rentria-chat-script', 'rentriaChatData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rentria_chat_nonce'),
                'pluginUrl' => RENTRIA_CHAT_PLUGIN_URL,
                'messages' => array(
                    'error' => __('Si è verificato un errore. Riprova più tardi.', 'rentria-chat'),
                    'copied' => __('Copiato!', 'rentria-chat'),
                    'copyError' => __('Errore copia', 'rentria-chat')
                )
            ));
        }
    }
    
    /**
     * Processa richieste chat via AJAX
     */
    public function process_chat_request() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rentria_chat_nonce')) {
            wp_die('Errore di sicurezza');
        }
        
        // Ottieni contenuto messaggio
        $content = sanitize_text_field($_POST['content']);
        
        if (empty($content)) {
            wp_send_json_error('Nessun contenuto ricevuto');
        }
        
        // Configurazione API
        $api_key = get_option('rentria_api_key', 'YPKwjwUhsEj6ygLXZ_G_NK1ugxOZ7XrS');
        $agent_id = get_option('rentria_agent_id', '5cec402c-6f7b-11f0-bf8f-4e013e2ddde4');
        $api_url = 'https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run/api/v1/chat/completions';
        
        // Prepara richiesta
        $data = array(
            'messages' => array(
                array('role' => 'user', 'content' => $content)
            ),
            'stream' => false // Per AJAX WordPress è meglio non usare streaming
        );
        
        // Esegui richiesta API
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Errore comunicazione API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            wp_send_json_success($result['choices'][0]['message']['content']);
        } else {
            wp_send_json_error('Risposta API non valida');
        }
    }
    
    /**
     * Aggiunge pagina di configurazione nel menu admin
     */
    public function add_admin_menu() {
        add_options_page(
            'RentrIA Chat Settings',
            'RentrIA Chat',
            'manage_options',
            'rentria-chat-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Pagina impostazioni plugin
     */
    public function settings_page() {
        // Salva impostazioni se inviate
        if (isset($_POST['submit'])) {
            update_option('rentria_api_key', sanitize_text_field($_POST['api_key']));
            update_option('rentria_agent_id', sanitize_text_field($_POST['agent_id']));
            echo '<div class="notice notice-success"><p>Impostazioni salvate!</p></div>';
        }
        
        $api_key = get_option('rentria_api_key', 'YPKwjwUhsEj6ygLXZ_G_NK1ugxOZ7XrS');
        $agent_id = get_option('rentria_agent_id', '5cec402c-6f7b-11f0-bf8f-4e013e2ddde4');
        ?>
        <div class="wrap">
            <h1>RentrIA Chat Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td><input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Agent ID</th>
                        <td><input type="text" name="agent_id" value="<?php echo esc_attr($agent_id); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Come usare</h2>
            <p>Usa lo shortcode <code>[rentria_chat]</code> in qualsiasi pagina o post.</p>
            
            <h3>Parametri disponibili:</h3>
            <ul>
                <li><code>height="650"</code> - Altezza del widget in pixel</li>
                <li><code>width="100%"</code> - Larghezza del widget</li>
                <li><code>max_width="600"</code> - Larghezza massima in pixel</li>
                <li><code>title="Assistente RentrIA"</code> - Titolo del widget</li>
                <li><code>placeholder="Scrivi un messaggio..."</code> - Testo placeholder input</li>
                <li><code>welcome="Ciao!"</code> - Messaggio di benvenuto</li>
                <li><code>show_pdf="true"</code> - Mostra pulsante export PDF</li>
                <li><code>show_copy="true"</code> - Mostra pulsante copia</li>
                <li><code>show_whatsapp="true"</code> - Mostra pulsante WhatsApp</li>
                <li><code>show_telegram="true"</code> - Mostra pulsante Telegram</li>
            </ul>
            
            <h3>Esempio:</h3>
            <code>[rentria_chat height="500" title="Chiedi all'assistente" show_pdf="false"]</code>
        </div>
        <?php
    }
}

// Inizializza plugin
new RentriaChatWidget();