#!/bin/bash
# Script per fermare RentriFacileBot

echo "🛑 Arresto RentriFacileBot..."

# Controlla se esiste il file PID
if [ -f "logs/bot.pid" ]; then
    PID=$(cat logs/bot.pid)
    echo "📋 PID trovato: $PID"
    
    # Controlla se il processo è ancora attivo
    if kill -0 $PID 2>/dev/null; then
        echo "🔄 Fermando il bot..."
        kill $PID
        
        # Aspetta che il processo si fermi
        sleep 2
        
        # Verifica che sia effettivamente fermato
        if kill -0 $PID 2>/dev/null; then
            echo "⚠️ Processo ancora attivo, forzo la chiusura..."
            kill -9 $PID
        fi
        
        echo "✅ Bot fermato con successo!"
    else
        echo "⚠️ Processo non trovato (potrebbe essere già fermato)"
    fi
    
    # Rimuovi il file PID
    rm logs/bot.pid
else
    echo "📋 File PID non trovato"
fi

# Controlla e ferma eventuali processi rimasti
if pgrep -f "polling-bot.php" > /dev/null; then
    echo "🔍 Trovati altri processi bot, li fermo..."
    pkill -f "polling-bot.php"
    echo "✅ Tutti i processi bot fermati"
else
    echo "✅ Nessun processo bot attivo"
fi

echo "🏁 RentriFacileBot arrestato completamente"