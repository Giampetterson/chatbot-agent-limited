#!/bin/bash
# Script per avviare RentriFacileBot

echo "🤖 Avvio RentriFacileBot..."

# Vai nella directory del bot
cd /var/www/lightbot.rentri360.it/public_html/telegram-bot

# Controlla se il bot è già in esecuzione
if pgrep -f "polling-bot.php" > /dev/null; then
    echo "⚠️ Bot già in esecuzione!"
    echo "🔍 PID: $(pgrep -f polling-bot.php)"
    echo "🛑 Per fermarlo: ./stop-bot.sh"
    exit 1
fi

# Avvia il bot in background
echo "🚀 Avvio bot in background..."
nohup php polling-bot.php > logs/bot-output.log 2>&1 &

# Salva il PID
echo $! > logs/bot.pid

sleep 2

# Verifica che sia avviato
if pgrep -f "polling-bot.php" > /dev/null; then
    echo "✅ Bot avviato con successo!"
    echo "📋 PID: $(cat logs/bot.pid)"
    echo "📊 Log: tail -f logs/bot-output.log"
    echo "🛑 Stop: ./stop-bot.sh"
    echo ""
    echo "🎉 RentriFacileBot è ora attivo nel gruppo @RentriFacile!"
    echo "💬 Testa con: @RentriFacileBot /start"
else
    echo "❌ Errore nell'avvio del bot"
    echo "📋 Controlla logs/bot-output.log per dettagli"
    exit 1
fi