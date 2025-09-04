# LIGHTBOT RENTRI360.it - Contesto Progetto

## 📋 Panoramica Sistema
**Lightbot** è un assistente virtuale AI specializzato nella gestione rifiuti e normative RENTRI, integrato con chat web e bot Telegram.

**URL Principale**: https://lightbot.rentri360.it/chatbot.php
**Dominio**: lightbot.rentri360.it
**Piattaforma**: Linux, Nginx, PHP 8.4, MySQL

---

## 🏗️ Architettura Applicazione

### **Frontend**
- **Chat Web**: `chatbot.php` (era chatbot.html, rinominato per headers PHP)
- **Scripts**: `user-fingerprint.js`, `voice-recorder.js`, `services.js`, `notes.js`
- **Redirect**: `index.html` → `chatbot.php`

### **Backend API**
- **Chat API**: `chat.php` - Endpoint principale per messaggi AI
- **Trascrizione Vocale**: `voice-transcription.php` - OpenAI Whisper (italiano)
- **Analisi Immagini**: `analyze-proxy.php`, `analyze-original.php` - OpenAI Vision
- **Bot Telegram**: `telegram-bot/polling-bot.php`, `bot-functions.php`

### **Database & Rate Limiting**
- **Database**: MySQL via `database.php` (DatabaseManager)
- **Schema**: Completo in `database/schema/lightbot_schema.sql`
- **Setup**: Script automatico `database/scripts/init-database.sh`
- **Rate Limiting**: `rate-limiter-db.php` - ATTUALMENTE DISATTIVATO (999999 msg)
- **User ID**: Fingerprinting via `user-fingerprint.js` + validazione `user-validator.php`
- **Backup**: Scripts automatici in `database/scripts/`

---

## 🔧 Configurazione Attuale

### **Rate Limiting Status**
- **LIMITE RIMOSSO**: Da 10 messaggi → 999999 messaggi per utente
- **Counter**: Nascosto nel frontend (`display: none`)
- **Database**: 8 utenti esistenti, tutti aggiornati a 999999

### **Sicurezza Headers (Safari iOS Fix)**
- **Headers attivi**: Permissions-Policy, CSP, CORS
- **File**: `.htaccess`, `security-headers.php` 
- **Problema risolto**: Accesso microfono su Safari iOS

### **AI Configuration**
- **Chat**: API personalizzata `https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run`
- **Trascrizione**: OpenAI Whisper con `language: 'it'`
- **Visione**: OpenAI GPT-4o per analisi immagini rifiuti
- **Context**: `contesto.txt` - Terminologia gestione rifiuti

---

## 🔍 User ID System
- **Formato**: `fp_[64char_hash]_[timestamp_base36]`
- **Fallback Mobile**: `fp_emergency_[random]_[timestamp]` 
- **Validazione**: `user-validator.php` - Fixed timestamp validation bug
- **Storage**: localStorage con validità 24h

---

## 📁 File Structure Principali
```
public_html/
├── chatbot.php              # Applicazione principale (era .html)
├── chat.php                 # API chat con streaming
├── voice-transcription.php  # API trascrizione vocale
├── analyze-proxy.php        # API analisi immagini
├── user-fingerprint.js      # Generazione User ID
├── voice-recorder.js        # Registrazione audio
├── rate-limiter-db.php      # Rate limiting (disattivato)
├── database.php            # Database manager
├── security-headers.php    # Headers sicurezza Safari iOS
├── .htaccess               # Config Apache/PHP limits
├── telegram-bot/
│   ├── polling-bot.php     # Bot Telegram
│   ├── bot-functions.php   # Funzioni bot
│   └── config.php          # Config bot
└── test-*.php              # File di test vari
```

---

## ⚙️ Configurazioni Environment

### **File .env** (Credenziali)
```bash
# AI APIs
OPENAI_API_KEY=sk-proj-U7V7TvzhFQcsSrjjfxaW_zT...
AI_API_URL=https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run/api/v1/chat/completions
AI_API_KEY=YPKwjwUhsEj6ygLXZ_G_NK1ugxOZ7XrS

# Telegram Bot
TELEGRAM_BOT_TOKEN=8105155308:AAFLNStazvpGz6j-ntEArmyyMWv1jZewkhs
TELEGRAM_BOT_USERNAME=RentriFacileBot

# Rate Limiting (DISATTIVATO)
RATE_LIMIT_MAX_MESSAGES=999999
RATE_LIMIT_MESSAGE="Servizio temporaneamente non disponibile. Riprova più tardi."

# Database
DB_HOST=localhost
DB_NAME=lightbot
DB_USER=lightbot_user
DB_PASSWORD=df65e00b78b87b10f724d3baaf88e7a9
```

---

## 🚨 Problemi Noti

### **HTTP 413 - Request Entity Too Large**
- **Causa**: Nginx `client_max_body_size` troppo basso per upload > 1.7MB
- **Fix Applicato**: PHP limits aumentati (10MB), controlli applicazione
- **Still TODO**: Configurazione Nginx (richiede sudo)

### **Safari iOS Microfono**
- **Status**: ✅ RISOLTO con Milestone 1
- **Fix**: Headers Permissions-Policy, CSP media-src, fallback fingerprinting

---

## 📊 Stato Database
```sql
-- Tabella user_limits
SELECT COUNT(*) as total_users, 
       SUM(is_blocked) as blocked_users, 
       AVG(count) as avg_messages 
FROM user_limits;
-- Result: 8 users, 0 blocked, 1.25 avg messages
```

---

## 🔧 Comandi Utili

### **Test Applicazione**
```bash
# Test chat API
curl -X POST https://lightbot.rentri360.it/chat.php \
  -H "Content-Type: application/json" \
  -H "X-User-ID: fp_test_$(date +%s)" \
  -d '{"content":"test"}'

# Test headers Safari
curl -I https://lightbot.rentri360.it/test-safari-headers.php

# Check logs
tail -f /var/log/nginx/lightbot.rentri360.it_error.log
```

### **Database Queries**
```sql
-- Reset user limits
UPDATE user_limits SET max_count = 999999 WHERE 1=1;

-- Check user status  
SELECT user_id_hash, count, max_count, is_blocked FROM user_limits;
```

---

## 🎯 Milestone Completate

1. **✅ Fix Error 400**: Risolto bug validazione timestamp user ID
2. **✅ Rimozione Limite**: 10 → 999999 messaggi, counter nascosto
3. **✅ Safari iOS Headers**: Permissions-Policy e CSP implementati
4. **✅ Blacklist Fix**: Rimossa parola "rentri" dai filtri di sicurezza

---

## 📝 Note Sviluppo
- **Fingerprinting**: Fallback per browser mobile con restrizioni API
- **Logging**: Sistema centralizzato con categorie (API, security, performance)  
- **Telegram**: Supporta polling e webhook, messaggi markdown
- **Sicurezza**: Protezione contro prompt injection, rate limiting, HTTPS

---

**Ultimo aggiornamento**: 2025-09-03
**Status**: ✅ OPERATIVO - https://lightbot.rentri360.it/chatbot.php