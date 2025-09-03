# 🤖 LIGHTBOT RENTRI360.it

**Assistente virtuale AI specializzato nella gestione rifiuti e normative RENTRI**

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=flat&logo=mysql)](https://mysql.com)
[![Nginx](https://img.shields.io/badge/Nginx-1.26.3-009639?style=flat&logo=nginx)](https://nginx.org)
[![Status](https://img.shields.io/badge/Status-🟢_Operativo-brightgreen)](https://lightbot.rentri360.it/chatbot.php)

## 🌐 Demo Live
**[https://lightbot.rentri360.it/chatbot.php](https://lightbot.rentri360.it/chatbot.php)**

---

## 📋 Panoramica

LIGHTBOT è un assistente virtuale alimentato da AI specializzato nella semplificazione delle procedure di gestione rifiuti e compliance normative RENTRI. Integra chat web multimodale, bot Telegram e funzionalità avanzate per l'analisi di immagini e trascrizione vocale.

### 🎯 Caratteristiche Principali

- **💬 Chat AI Avanzata**: Streaming in tempo reale con expertise specializzata in gestione rifiuti
- **🎤 Trascrizione Vocale**: OpenAI Whisper ottimizzato per italiano  
- **📸 Analisi Immagini**: Riconoscimento AI di rifiuti con classificazione CER
- **♻️ Classificazione RENTRI**: Generazione automatica codici e procedure
- **📱 Bot Telegram**: Integrazione completa con gruppo `@rentrifacile`
- **📝 Sistema Appunti**: Salvataggio e esportazione PDF delle conversazioni
- **🔐 Rate Limiting**: Gestione utenti con fingerprinting avanzato

---

## 🏗️ Architettura

### Frontend
- **Interfaccia Web**: `chatbot.php` - Chat responsiva con UI/UX ottimizzata
- **Scripts Core**: `user-fingerprint.js`, `voice-recorder.js`, `services.js`, `notes.js`
- **Redirect**: `index.html` → `chatbot.php`

### Backend API
- **Chat API**: `chat.php` - Endpoint principale con streaming SSE
- **Trascrizione**: `voice-transcription.php` - OpenAI Whisper italiano
- **Analisi Immagini**: `analyze-proxy.php` - OpenAI GPT-4o Vision
- **Bot Telegram**: `telegram-bot/polling-bot.php` + `bot-functions.php`

### Database & Security  
- **Database**: MySQL via `DatabaseManager` class
- **Rate Limiting**: Sistema basato su fingerprinting utente (attualmente 999999 msg/utente)
- **Security Headers**: CSP, CORS, Permissions-Policy per Safari iOS
- **User ID**: Formato `fp_[64char_hash]_[timestamp_base36]`

---

## ⚙️ Requisiti di Sistema

- **PHP**: 8.4+ con estensioni `curl`, `json`, `mysql`, `gd`
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Web Server**: Nginx 1.20+ / Apache 2.4+
- **SSL**: Certificato valido (Let's Encrypt consigliato)
- **Memoria**: 256MB+ PHP memory limit
- **Upload**: 10MB+ `client_max_body_size` (Nginx)

---

## 🚀 Installazione

### 1. Clone Repository
```bash
git clone https://github.com/Giampetterson/chatbot-agent-limited.git
cd chatbot-agent-limited
```

### 2. Configurazione Database
```sql
CREATE DATABASE lightbot;
CREATE USER 'lightbot_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON lightbot.* TO 'lightbot_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. File Environment
Crea `.env` nella root del progetto:

```bash
# AI APIs
OPENAI_API_KEY=sk-proj-your_openai_key
AI_API_URL=https://your-custom-ai-api.run/api/v1/chat/completions
AI_API_KEY=your_ai_api_key

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=YourBotUsername

# Rate Limiting
RATE_LIMIT_MAX_MESSAGES=999999
RATE_LIMIT_MESSAGE="Servizio temporaneamente non disponibile. Riprova più tardi."

# Database
DB_HOST=localhost
DB_NAME=lightbot
DB_USER=lightbot_user
DB_PASSWORD=your_password
```

### 4. Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /path/to/project/public_html;
    index index.html index.php;
    
    # Upload size limit
    client_max_body_size 10M;
    
    # SSL Configuration
    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;
    
    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Streaming optimization
        fastcgi_buffering off;
        proxy_buffering off;
        fastcgi_read_timeout 300s;
    }
    
    # Cache control for HTML
    location ~* \.(html|htm)$ {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
        add_header Expires "0";
    }
}
```

### 5. Permissions
```bash
chown -R www-data:www-data /path/to/project
chmod -R 755 /path/to/project
chmod 644 .env
```

---

## 🔧 Configurazione APIs

### OpenAI Integration
- **Chat**: API personalizzata compatibile OpenAI
- **Whisper**: Trascrizione vocale con `language: 'it'`
- **Vision**: GPT-4o per analisi immagini rifiuti
- **Context**: File `contesto.txt` con terminologia specializzata

### Telegram Bot Setup
1. Crea bot con [@BotFather](https://t.me/botfather)
2. Ottieni token e username
3. Configura webhook o polling in `telegram-bot/config.php`
4. Testa con `/start` command

---

## 📊 Monitoraggio & Logs

### Sistema di Logging
- **API Logs**: `logs/api/YYYY-MM-DD.log`
- **Performance**: `logs/performance/YYYY-MM-DD.log`  
- **Security**: `logs/security/YYYY-MM-DD.log`
- **General**: `logs/general/YYYY-MM-DD.log`
- **Error Tracking**: `logs/error_occurrences_YYYY-MM-DD.jsonl`

### Metriche Database
```sql
-- Status utenti
SELECT COUNT(*) as total_users, 
       SUM(is_blocked) as blocked_users, 
       AVG(count) as avg_messages 
FROM user_limits;

-- Reset limiti (se necessario)
UPDATE user_limits SET max_count = 999999 WHERE 1=1;
```

---

## 🧪 Testing

### Test Chat API
```bash
curl -X POST https://your-domain.com/chat.php \
  -H "Content-Type: application/json" \
  -H "X-User-ID: fp_test_$(date +%s)" \
  -d '{"content":"test message"}'
```

### Test Trascrizione
```bash
curl -X POST https://your-domain.com/voice-transcription.php \
  -H "X-User-ID: fp_test_$(date +%s)" \
  -F "audio=@test.webm"
```

### Test Headers Safari  
```bash
curl -I https://your-domain.com/chatbot.php
```

---

## 🛠️ Struttura File Principali

```
public_html/
├── chatbot.php              # Applicazione principale  
├── chat.php                 # API chat con streaming
├── voice-transcription.php  # API trascrizione vocale
├── analyze-proxy.php        # API analisi immagini
├── user-fingerprint.js      # Generazione User ID
├── voice-recorder.js        # Registrazione audio
├── services.js             # Pulsanti azione e condivisione
├── notes.js                # Sistema appunti
├── database.php            # Database manager
├── security-headers.php    # Headers sicurezza
├── .htaccess               # Config Apache/PHP
└── telegram-bot/
    ├── polling-bot.php     # Bot Telegram
    ├── bot-functions.php   # Funzioni bot
    └── config.php          # Config bot
```

---

## 🔒 Sicurezza

### Features Implementate
- **CSP Headers**: Content Security Policy restrictive
- **CORS**: Configurato per domini autorizzati
- **Rate Limiting**: Protezione da spam/abuse
- **Input Sanitization**: Validazione rigorosa input
- **Error Handling**: Logging dettagliato senza esposizione dati
- **Prompt Injection**: Protezioni against jailbreaking
- **File Upload**: Validazione tipo e dimensione file

### Raccomandazioni
- Aggiorna regolarmente PHP e dipendenze
- Monitora logs per attività sospette  
- Backup regolari database e `.env`
- Usa HTTPS con certificati validi
- Implementa fail2ban per protezione IP

---

## 📈 Performance

### Ottimizzazioni Applicate
- **Streaming SSE**: Risposta chat in tempo reale
- **FastCGI Buffering**: Disabilitato per streaming
- **Image Compression**: Client-side prima upload  
- **Cache Headers**: Controllo cache browser
- **Database Indexing**: Ottimizzato query rate limiting
- **Error Tracking**: JSON Lines per performance logs

### Metriche Monitorate
- **Response Time**: API calls >1000ms flagged as slow
- **Memory Usage**: PHP memory peaks
- **Database Queries**: Slow query detection
- **Error Rates**: Tracking per categoria errore

---

## 🤝 Contributi

### Development Workflow
1. Fork repository
2. Crea feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m '🚀 Add amazing feature'`)
4. Push branch (`git push origin feature/amazing-feature`)  
5. Open Pull Request

### Coding Standards
- **PSR-12**: PHP coding standard
- **ESLint**: JavaScript linting
- **Semantic Commits**: Conventional commit messages
- **Documentation**: Inline comments per funzioni complesse

---

## 📜 Changelog

### v2.4.4 - 2025-09-03
- 🎨 UI/UX improvements con pulsanti azione puliti
- ♻️ Nuova icona riciclo per pulsante RENTRI  
- 📏 Icone ingrandite a 32px per migliore accessibilità
- 🔧 Fix HTTP 413 con aumento `client_max_body_size` 10MB
- 🚀 Cache control headers per refresh forzato browser

### v2.4.3 - Previous Versions
- 🔒 Fix vulnerabilità system prompt disclosure
- 🔄 Aggiornamento firma bot Telegram Rentri360.it
- 🎯 Reset database e aumento limite messaggi
- 📱 Major UI/UX improvements mobile

---

## 📄 Licenza

Questo progetto è proprietario. Tutti i diritti riservati.

**© 2025 Rentri360.it - Piattaforma per la gestione rifiuti e compliance normative**

---

## 📞 Supporto

- **Demo Live**: [lightbot.rentri360.it](https://lightbot.rentri360.it/chatbot.php)
- **Telegram**: [@rentrifacile](https://t.me/rentrifacile)  
- **Issues**: [GitHub Issues](https://github.com/Giampetterson/chatbot-agent-limited/issues)
- **Documentation**: `CONTESTO_PROGETTO.md` per dettagli tecnici completi

---

*🤖 README generato con [Claude Code](https://claude.ai/code)*