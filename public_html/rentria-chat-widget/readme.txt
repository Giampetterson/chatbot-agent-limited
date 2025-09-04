=== RentrIA Chat Widget ===
Contributors: rentria
Tags: chat, ai, assistant, chatbot, widget
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: Proprietary
License URI: https://piattaformarentriFacile.it/license

Widget chat assistente virtuale RentrIA specializzato in gestione rifiuti e sistema RENTRI per WordPress con supporto streaming AI real-time.

== Description ==

RentrIA Chat Widget è un potente plugin WordPress che integra un assistente virtuale AI specializzato nella gestione dei rifiuti e sistema RENTRI direttamente nel tuo sito web. Il plugin offre supporto per classificazione rifiuti, normative ambientali e procedure RENTRI con interfaccia chat moderna e responsive.

= Caratteristiche principali =

* **Chat AI Real-time**: Risposte immediate con streaming progressivo
* **Interfaccia Moderna**: Design pulito e responsive
* **Rendering Markdown**: Supporto completo per formattazione avanzata
* **Export PDF**: Salva le conversazioni in formato PDF
* **Condivisione Social**: Integrazione WhatsApp e Telegram
* **Copia Testo**: Copia facilmente le risposte
* **Personalizzabile**: Molteplici opzioni di configurazione

= Funzionalità tecniche =

* Streaming SSE (Server-Sent Events)
* Compatibilità multi-browser
* Design responsive mobile-first
* Gestione errori robusta
* Cache intelligente

== Installation ==

1. Carica la cartella `rentria-chat-widget` nella directory `/wp-content/plugins/`
2. Attiva il plugin attraverso il menu 'Plugin' in WordPress
3. Configura le API keys dal menu Impostazioni → RentrIA Chat
4. Usa lo shortcode `[rentria_chat]` in qualsiasi pagina o post

= Configurazione API =

1. Vai su Impostazioni → RentrIA Chat
2. Inserisci la tua API Key
3. Inserisci l'Agent ID
4. Salva le impostazioni

== Usage ==

= Shortcode Base =

`[rentria_chat]`

= Shortcode con Parametri =

`[rentria_chat height="500" title="Assistente AI" show_pdf="true"]`

= Parametri Disponibili =

* `height` - Altezza del widget in pixel (default: 650)
* `width` - Larghezza del widget (default: 100%)
* `max_width` - Larghezza massima in pixel (default: 600)
* `title` - Titolo del widget (default: "Assistente RentrIA")
* `placeholder` - Testo placeholder input (default: "Scrivi un messaggio...")
* `welcome` - Messaggio di benvenuto iniziale
* `show_pdf` - Mostra pulsante export PDF (true/false)
* `show_copy` - Mostra pulsante copia (true/false)
* `show_whatsapp` - Mostra pulsante WhatsApp (true/false)
* `show_telegram` - Mostra pulsante Telegram (true/false)

== Examples ==

= Chat Semplice =
`[rentria_chat]`

= Chat Compatta =
`[rentria_chat height="400" max_width="500"]`

= Chat Senza Export =
`[rentria_chat show_pdf="false" show_whatsapp="false" show_telegram="false"]`

= Chat Personalizzata =
`[rentria_chat title="Chiedi all'esperto" welcome="Come posso aiutarti?" placeholder="Fai una domanda..."]`

== Frequently Asked Questions ==

= Posso usare più widget nella stessa pagina? =

Sì, il plugin supporta istanze multiple. Ogni widget avrà il proprio ID univoco.

= È richiesto HTTPS? =

No, ma è consigliato per alcune funzionalità come la copia negli appunti su alcuni browser.

= Quali browser sono supportati? =

Tutti i browser moderni: Chrome, Firefox, Safari, Edge. IE11 ha supporto limitato.

= Posso personalizzare i colori? =

Sì, puoi sovrascrivere gli stili CSS nel tuo tema.

= Il plugin funziona con page builders? =

Sì, è compatibile con Elementor, Gutenberg, Divi e altri page builders tramite shortcode.

== Screenshots ==

1. Widget chat in azione
2. Pannello di configurazione
3. Esempio di conversazione
4. Export PDF
5. Condivisione social

== Changelog ==

= 1.0.0 =
* Prima release pubblica
* Interfaccia chat completa
* Integrazione API AI
* Export PDF
* Condivisione WhatsApp e Telegram
* Pannello amministrazione

== Technical Notes ==

= Requisiti Server =
* PHP 7.4+
* WordPress 5.0+
* Connessione internet per API
* 64MB memoria PHP (consigliato)

= API Endpoints =
* Chat API: https://agents.do-ai.run/api/v1/chat/completions
* Metodo: POST con streaming SSE

= File Structure =
```
rentria-chat-widget/
├── rentria-chat-widget.php (Main plugin file)
├── assets/
│   ├── rentria-chat.css
│   └── rentria-chat.js
└── readme.txt
```

== Support ==

Per supporto tecnico: https://piattaformarentriFacile.it/supporto

== Privacy ==

Questo plugin invia dati a servizi esterni:
* API AI per elaborazione messaggi
* Font Google per tipografia

I dati degli utenti non vengono salvati localmente.