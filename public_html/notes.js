/**
 * Notes Manager - Gestione appunti per RentrIA Chat
 * Permette di salvare risposte del bot in un pannello laterale
 */

/**
 * Image Compressor - Gestione compressione immagini
 * Comprime e ridimensiona immagini per ottimizzare lo storage
 */
class ImageCompressor {
  constructor() {
    this.maxWidth = 1024;
    this.maxHeight = 1024;
    this.quality = 0.8;
    this.maxSizeBytes = 5 * 1024 * 1024; // 5MB
  }

  /**
   * Comprime un file immagine
   * @param {File} file - File immagine da comprimere
   * @returns {Promise<Object>} - Oggetto con dati immagine compressa
   */
  async compressImage(file) {
    return new Promise((resolve, reject) => {
      // Validazione file
      if (!this.isValidImage(file)) {
        reject(new Error('Formato file non supportato. Usa PNG, JPEG, WebP o GIF.'));
        return;
      }

      if (file.size > this.maxSizeBytes) {
        reject(new Error(`File troppo grande. Massimo ${this.maxSizeBytes / (1024 * 1024)}MB.`));
        return;
      }

      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          try {
            const compressed = this.processImage(img, file.name);
            resolve(compressed);
          } catch (error) {
            reject(error);
          }
        };
        img.onerror = () => reject(new Error('Errore nel caricamento immagine'));
        img.src = e.target.result;
      };
      reader.onerror = () => reject(new Error('Errore nella lettura del file'));
      reader.readAsDataURL(file);
    });
  }

  /**
   * Processa e comprime l'immagine usando Canvas
   * @param {HTMLImageElement} img - Immagine caricata
   * @param {string} fileName - Nome file originale
   * @returns {Object} - Dati immagine compressa
   */
  processImage(img, fileName) {
    // Calcola dimensioni mantenendo aspect ratio
    const { width, height } = this.calculateDimensions(img.width, img.height);
    
    // Crea canvas
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = width;
    canvas.height = height;

    // Disegna immagine ridimensionata
    ctx.fillStyle = '#FFFFFF'; // Sfondo bianco per JPEG
    ctx.fillRect(0, 0, width, height);
    ctx.drawImage(img, 0, 0, width, height);

    // Determina formato output
    const outputFormat = this.getOptimalFormat(fileName);
    
    // Comprimi e converti in base64
    const compressedDataUrl = canvas.toDataURL(outputFormat, this.quality);
    
    return {
      id: 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
      dataUrl: compressedDataUrl,
      fileName: fileName,
      format: outputFormat,
      originalSize: { width: img.width, height: img.height },
      compressedSize: { width, height },
      timestamp: new Date().toISOString(),
      sizeBytes: Math.round((compressedDataUrl.length - 'data:image/jpeg;base64,'.length) * 3/4)
    };
  }

  /**
   * Calcola dimensioni ottimali mantenendo aspect ratio
   * @param {number} originalWidth - Larghezza originale
   * @param {number} originalHeight - Altezza originale
   * @returns {Object} - Nuove dimensioni
   */
  calculateDimensions(originalWidth, originalHeight) {
    let { width, height } = { width: originalWidth, height: originalHeight };
    
    if (width > this.maxWidth) {
      height = (height * this.maxWidth) / width;
      width = this.maxWidth;
    }
    
    if (height > this.maxHeight) {
      width = (width * this.maxHeight) / height;
      height = this.maxHeight;
    }
    
    return { width: Math.round(width), height: Math.round(height) };
  }

  /**
   * Determina il formato ottimale per l'output
   * @param {string} fileName - Nome file originale
   * @returns {string} - MIME type per output
   */
  getOptimalFormat(fileName) {
    const ext = fileName.toLowerCase().split('.').pop();
    
    // Mantieni PNG per immagini con trasparenza
    if (ext === 'png') {
      return 'image/png';
    }
    
    // Usa WebP se supportato, altrimenti JPEG
    const canvas = document.createElement('canvas');
    const webpSupported = canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    
    return webpSupported ? 'image/webp' : 'image/jpeg';
  }

  /**
   * Valida se il file è un'immagine supportata
   * @param {File} file - File da validare
   * @returns {boolean} - True se valido
   */
  isValidImage(file) {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    return validTypes.includes(file.type);
  }
}

class NotesManager {
  constructor() {
    this.notes = [];
    this.dossiers = [];
    this.isOpen = false;
    this.storageKey = 'rentria_chat_notes';
    this.dossiersStorageKey = 'rentria_chat_dossiers';
    this.maxNotes = 50;
    this.maxDossiers = 20;
    this.maxImagesPerNote = 10;
    this.defaultDossierId = 'default-notes-dossier';
    this.imageCompressor = new ImageCompressor();
    this.init();
  }

  init() {
    // Carica note e dossier salvati dal localStorage
    this.loadDossiers();
    this.loadNotes();
    
    // Crea il dossier di default se non esiste
    this.ensureDefaultDossier();
    
    // Attendi che il DOM sia pronto
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        this.initializePanel();
      });
    } else {
      this.initializePanel();
    }
  }
  
  ensureDefaultDossier() {
    // Verifica se il dossier di default esiste
    let defaultDossier = this.dossiers.find(d => d.id === this.defaultDossierId);
    
    if (!defaultDossier) {
      // Crea il dossier di default
      defaultDossier = {
        id: this.defaultDossierId,
        name: '📝 Note Generali',
        created: new Date().toISOString(),
        noteIds: [],
        isDefault: true // Flag per identificarlo come non cancellabile
      };
      
      // Aggiungi all'inizio della lista
      this.dossiers.unshift(defaultDossier);
      this.saveDossiers();
    } else {
      // MIGRAZIONE: Se esiste ma non ha il flag isDefault, aggiungilo
      if (defaultDossier.isDefault !== true) {
        defaultDossier.isDefault = true;
        this.saveDossiers();
      }
    }
    
    // Sposta tutte le note senza dossier nel dossier di default
    this.notes.forEach(note => {
      if (!note.dossierId || note.dossierId === null) {
        note.dossierId = this.defaultDossierId;
        if (!defaultDossier.noteIds.includes(note.id)) {
          defaultDossier.noteIds.push(note.id);
        }
      }
    });
    
    if (this.notes.some(n => !n.dossierId || n.dossierId === null)) {
      this.saveNotes();
      this.saveDossiers();
    }
  }
  
  forceRefresh() {
    // Feedback visivo sul pulsante refresh
    const refreshBtn = document.querySelector('.notes-refresh-btn');
    if (refreshBtn) {
      const originalHTML = refreshBtn.innerHTML;
      refreshBtn.style.transform = 'rotate(360deg)';
      refreshBtn.style.transition = 'transform 0.5s ease';
    }
    
    // Ricarica tutto dal localStorage
    this.loadDossiers();
    this.loadNotes();
    
    // Assicura dossier default
    this.ensureDefaultDossier();
    
    // DISTRUGGI E RICREA completamente il pannello
    const existingPanel = document.getElementById('notes-panel');
    if (existingPanel) {
      existingPanel.remove();
    }
    
    // Ricrea da zero
    this.createNotesPanel();
    this.setupEventListeners();
    
    // Aggiorna tutto
    this.updateNotesDisplay();
    this.updateBadge();
    
    // Reset animazione refresh
    setTimeout(() => {
      const newRefreshBtn = document.querySelector('.notes-refresh-btn');
      if (newRefreshBtn) {
        newRefreshBtn.style.transform = 'rotate(0deg)';
      }
    }, 500);
  }
  
  initializePanel() {
    // Crea il pannello appunti se non esiste
    if (!document.getElementById('notes-panel')) {
      this.createNotesPanel();
    }
    
    // Inizializza event listeners
    this.setupEventListeners();
    
    // Aggiorna display iniziale
    this.updateNotesDisplay();
    this.updateBadge();
  }

  createNotesPanel() {
    // Container principale del pannello
    const panel = document.createElement('div');
    panel.id = 'notes-panel';
    panel.className = 'notes-panel closed';
    
    // Header del pannello
    const header = document.createElement('div');
    header.className = 'notes-header';
    header.innerHTML = `
      <h3>📝 Note</h3>
      <div class="notes-controls">
        <span class="notes-count">${this.notes.length} note, ${this.dossiers.length} dossier</span>
        <button class="notes-refresh-btn" title="Aggiorna appunti">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
          </svg>
        </button>
        <button class="dossier-add-btn" title="Crea nuovo dossier">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            <line x1="12" y1="11" x2="12" y2="17"></line>
            <line x1="9" y1="14" x2="15" y2="14"></line>
          </svg>
        </button>
        <button class="notes-clear-btn" title="Cancella tutto">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          </svg>
        </button>
      </div>
    `;
    
    // Area principale con dossier e note
    const mainArea = document.createElement('div');
    mainArea.className = 'notes-main-area';
    mainArea.id = 'notes-main-area';
    
    // Footer con azioni
    const footer = document.createElement('div');
    footer.className = 'notes-footer';
    footer.innerHTML = `
      <button class="notes-export-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        Esporta Appunti
      </button>
    `;
    
    // Assembla il pannello
    panel.appendChild(header);
    panel.appendChild(mainArea);
    panel.appendChild(footer);
    
    // Aggiungi al documento
    document.body.appendChild(panel);
  }

  setupEventListeners() {
    // Pulsante per aprire/chiudere (ora è il pulsante Note nel form)
    const notesBtn = document.getElementById('notes-btn');
    if (notesBtn) {
      notesBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.togglePanel();
      });
    }
    
    // Pulsante refresh
    const refreshBtn = document.querySelector('.notes-refresh-btn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => this.forceRefresh());
      console.log('Pulsante refresh trovato e collegato');
    } else {
      console.log('ERRORE: Pulsante refresh NON trovato!');
    }
    
    // Pulsante crea dossier
    const addDossierBtn = document.querySelector('.dossier-add-btn');
    if (addDossierBtn) {
      addDossierBtn.addEventListener('click', () => this.createNewDossier());
    }
    
    // Pulsante cancella tutto
    const clearBtn = document.querySelector('.notes-clear-btn');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => this.clearAll());
    }
    
    // Pulsante esporta
    const exportBtn = document.querySelector('.notes-export-btn');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => this.exportNotes());
    }
    
    // Click fuori dal pannello per chiuderlo
    document.addEventListener('click', (e) => {
      const panel = document.getElementById('notes-panel');
      const notesBtn = document.getElementById('notes-btn');
      const isClickInside = panel && panel.contains(e.target);
      const isNotesButton = notesBtn && notesBtn.contains(e.target);
      const isAddButton = e.target.closest('.add-to-notes-btn');
      
      if (!isClickInside && !isNotesButton && !isAddButton && this.isOpen) {
        this.closePanel();
      }
    });
  }

  addNote(content, messageId) {
    // Crea nuovo oggetto nota
    const note = {
      id: 'note-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
      content: content,
      messageId: messageId,
      timestamp: new Date().toISOString(),
      date: new Date().toLocaleString('it-IT'),
      dossierId: this.defaultDossierId, // Inizialmente va nel dossier di default
      images: [] // Array per le immagini associate alla nota
    };
    
    // Aggiungi alla lista
    this.notes.unshift(note);
    
    // Aggiungi al dossier di default
    const defaultDossier = this.dossiers.find(d => d.id === this.defaultDossierId);
    if (defaultDossier && !defaultDossier.noteIds.includes(note.id)) {
      defaultDossier.noteIds.unshift(note.id);
    }
    
    // Limita il numero di note
    if (this.notes.length > this.maxNotes) {
      const removedNotes = this.notes.slice(this.maxNotes);
      this.notes = this.notes.slice(0, this.maxNotes);
      
      // Rimuovi anche dai dossier le note eliminate
      removedNotes.forEach(removedNote => {
        this.dossiers.forEach(dossier => {
          dossier.noteIds = dossier.noteIds.filter(id => id !== removedNote.id);
        });
      });
    }
    
    // Salva nel localStorage
    this.saveNotes();
    this.saveDossiers();
    
    // Aggiorna UI
    this.updateNotesDisplay();
    this.updateBadge();
    
    // Mostra feedback
    this.showAddedFeedback();
    
    // Non apriamo più automaticamente il pannello
    
    return note.id;
  }

  removeNote(noteId) {
    const index = this.notes.findIndex(n => n.id === noteId);
    if (index > -1) {
      const note = this.notes[index];
      
      // Rimuovi la nota dalla lista
      this.notes.splice(index, 1);
      
      // Rimuovi anche l'ID dai dossier
      if (note.dossierId) {
        const dossier = this.dossiers.find(d => d.id === note.dossierId);
        if (dossier) {
          dossier.noteIds = dossier.noteIds.filter(id => id !== noteId);
        }
      }
      
      // Se non ci sono più note, assicurati che il dossier di default esista comunque
      if (this.notes.length === 0) {
        this.ensureDefaultDossier();
      }
      
      this.saveNotes();
      this.saveDossiers();
      this.updateNotesDisplay();
      this.updateBadge();
    }
  }

  createNewDossier() {
    const name = prompt('Nome del nuovo dossier:', 'Nuovo Dossier');
    if (!name || !name.trim()) return;
    
    const dossier = {
      id: 'dossier-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
      name: name.trim(),
      created: new Date().toISOString(),
      noteIds: []
    };
    
    this.dossiers.unshift(dossier);
    
    // Limita numero dossier
    if (this.dossiers.length > this.maxDossiers) {
      this.dossiers = this.dossiers.slice(0, this.maxDossiers);
    }
    
    this.saveDossiers();
    this.updateNotesDisplay();
    this.updateBadge();
    
    return dossier.id;
  }

  removeDossier(dossierId) {
    // Trova il dossier da eliminare
    const dossier = this.dossiers.find(d => d.id === dossierId);
    
    // Non permettere la cancellazione del dossier di default
    if (dossierId === this.defaultDossierId || (dossier && dossier.isDefault === true)) {
      alert('Il dossier "Note Generali" non può essere eliminato.');
      return;
    }
    
    const index = this.dossiers.findIndex(d => d.id === dossierId);
    if (index > -1) {
      // Sposta le note del dossier eliminato nel dossier di default
      const defaultDossier = this.dossiers.find(d => d.id === this.defaultDossierId);
      this.notes.forEach(note => {
        if (note.dossierId === dossierId) {
          note.dossierId = this.defaultDossierId;
          if (defaultDossier && !defaultDossier.noteIds.includes(note.id)) {
            defaultDossier.noteIds.push(note.id);
          }
        }
      });
      
      this.dossiers.splice(index, 1);
      this.saveDossiers();
      this.saveNotes();
      this.updateNotesDisplay();
      this.updateBadge();
    }
  }

  moveNoteToDossier(noteId, dossierId) {
    const note = this.notes.find(n => n.id === noteId);
    if (!note) return false;
    
    // Rimuovi dalla posizione precedente
    if (note.dossierId) {
      const oldDossier = this.dossiers.find(d => d.id === note.dossierId);
      if (oldDossier) {
        oldDossier.noteIds = oldDossier.noteIds.filter(id => id !== noteId);
      }
    }
    
    // Aggiungi al nuovo dossier
    if (dossierId) {
      const dossier = this.dossiers.find(d => d.id === dossierId);
      if (dossier) {
        if (!dossier.noteIds.includes(noteId)) {
          dossier.noteIds.push(noteId);
        }
        note.dossierId = dossierId;
      }
    } else {
      // Se dossierId è null, sposta nel dossier di default
      note.dossierId = this.defaultDossierId;
      const defaultDossier = this.dossiers.find(d => d.id === this.defaultDossierId);
      if (defaultDossier && !defaultDossier.noteIds.includes(noteId)) {
        defaultDossier.noteIds.push(noteId);
      }
    }
    
    this.saveNotes();
    this.saveDossiers();
    this.updateNotesDisplay();
    
    return true;
  }

  clearAll() {
    if (this.notes.length === 0) return;
    
    if (confirm('Sei sicuro di voler cancellare tutti gli appunti? (I dossier vuoti verranno mantenuti)')) {
      // Cancella solo le note, NON i dossier
      this.notes = [];
      
      // Pulisci i riferimenti nei dossier ma mantieni i dossier
      this.dossiers.forEach(dossier => {
        dossier.noteIds = [];
      });
      
      // Assicurati che il dossier di default esista comunque
      this.ensureDefaultDossier();
      
      // Salva tutto
      this.saveNotes();
      this.saveDossiers();
      
      // FORCE REFRESH per assicurarsi che tutto si veda
      this.forceRefresh();
      
      this.showClearedFeedback();
    }
  }

  updateNotesDisplay() {
    const mainArea = document.getElementById('notes-main-area');
    if (!mainArea) return;
    
    // Assicurati sempre che il dossier di default esista
    this.ensureDefaultDossier();
    
    let html = '';
    
    // Sezione Dossier
    if (this.dossiers.length > 0) {
      html += '<div class="dossiers-section"><h4>📁 Dossier</h4>';
      
      this.dossiers.forEach(dossier => {
        const dossierNotes = this.notes.filter(note => note.dossierId === dossier.id);
        const isDefault = dossier.id === this.defaultDossierId || dossier.isDefault === true;
        
        
        
        html += `
          <div class="dossier-item" data-dossier-id="${dossier.id}">
            <div class="dossier-header">
              <span class="dossier-name">${dossier.name}</span>
              <span class="dossier-count">(${dossierNotes.length})</span>
              ${!isDefault ? `
                <button class="dossier-remove-btn" data-dossier-id="${dossier.id}" title="Elimina dossier">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              ` : ''}
            </div>
            <div class="dossier-drop-zone" data-dossier-id="${dossier.id}">
              ${dossierNotes.length === 0 ? '<div class="drop-placeholder">Trascina qui le note</div>' : ''}
              ${dossierNotes.map(note => this.renderNoteItem(note)).join('')}
            </div>
          </div>
        `;
      });
      
      html += '</div>';
    }
    
    // Se non ci sono dossier (caso improbabile), mostra messaggio vuoto
    if (this.dossiers.length === 0) {
      html = '<div class="notes-empty">Nessun appunto salvato</div>';
    }
    
    mainArea.innerHTML = html;
    
    // Aggiungi event listeners
    this.attachEventListeners();
  }

  renderNoteItem(note) {
    // Aggiungi opzioni per mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Crea select per dossier se siamo su mobile
    let dossierSelect = '';
    if (isMobile && this.dossiers.length > 0) {
      dossierSelect = `
        <select class="note-dossier-select" data-note-id="${note.id}">
          <option value="">📝 Note Libere</option>
          ${this.dossiers.map(d => 
            `<option value="${d.id}" ${note.dossierId === d.id ? 'selected' : ''}>📁 ${d.name}</option>`
          ).join('')}
        </select>
      `;
    }
    
    return `
      <div class="note-item" data-note-id="${note.id}" draggable="true">
        <div class="note-header">
          <span class="note-date">${note.date}</span>
          <div class="note-header-actions">
            ${!isMobile ? '<span class="drag-handle" title="Trascina per spostare">⋮⋮</span>' : ''}
            <button class="note-remove-btn" data-note-id="${note.id}" title="Rimuovi appunto">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </div>
        ${dossierSelect}
        <div class="note-content">
          <div class="note-preview">${this.formatNoteContent(note.content)}</div>
          <div class="note-full" style="display: none;">${this.formatFullContent(note.content)}</div>
        </div>
        ${this.renderNoteImages(note)}
        <div class="note-actions">
          <button class="note-upload-btn" data-note-id="${note.id}" title="Aggiungi immagini">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="9" cy="9" r="2"/>
              <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
            </svg>
            <span class="btn-text">Immagini</span>
          </button>
          <button class="note-copy-btn" data-note-id="${note.id}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span class="btn-text">Copia</span>
          </button>
        </div>
      </div>
    `;
  }

  /**
   * Renderizza le immagini di una nota
   * @param {Object} note - Oggetto nota
   * @returns {string} - HTML per le immagini
   */
  renderNoteImages(note) {
    if (!note.images || note.images.length === 0) {
      return '';
    }

    const thumbnails = note.images.map((image, index) => `
      <div class="note-image-thumbnail" data-note-id="${note.id}" data-image-id="${image.id}" data-index="${index}">
        <img src="${image.dataUrl}" alt="${image.fileName}" loading="lazy">
        <div class="thumbnail-overlay">
          <button class="thumbnail-view" title="Visualizza">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
          <button class="thumbnail-remove" title="Rimuovi">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <div class="thumbnail-info">
          <span class="thumbnail-size">${image.compressedSize.width}×${image.compressedSize.height}</span>
        </div>
      </div>
    `).join('');

    return `
      <div class="note-images" data-note-id="${note.id}">
        <div class="images-header">
          <span class="images-count">${note.images.length} ${note.images.length === 1 ? 'immagine' : 'immagini'}</span>
          <button class="images-expand-btn" title="Espandi">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>
        </div>
        <div class="images-grid">
          ${thumbnails}
          <div class="add-image-drop-zone" data-note-id="${note.id}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Trascina qui</span>
          </div>
        </div>
      </div>
    `;
  }

  attachEventListeners() {
    // Event listeners per pulsanti rimozione note
    document.querySelectorAll('.note-remove-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.removeNote(btn.dataset.noteId);
      });
    });

    // Event listeners per pulsanti copia
    document.querySelectorAll('.note-copy-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const note = this.notes.find(n => n.id === btn.dataset.noteId);
        if (note) {
          this.copyNoteToClipboard(note.content, btn);
        }
      });
    });

    // Event listeners per pulsanti rimozione dossier
    document.querySelectorAll('.dossier-remove-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (confirm('Eliminare il dossier? Le note torneranno libere.')) {
          this.removeDossier(btn.dataset.dossierId);
        }
      });
    });
    
    // Event listeners per select dossier su mobile
    document.querySelectorAll('.note-dossier-select').forEach(select => {
      select.addEventListener('change', (e) => {
        const noteId = select.dataset.noteId;
        const dossierId = select.value || null;
        this.moveNoteToDossier(noteId, dossierId);
        this.showMovedFeedback();
      });
    });

    // Click su note per espandere/collassare
    document.querySelectorAll('.note-item').forEach(item => {
      const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      
      // Setup drag and drop solo su desktop
      if (!isMobile) {
        this.setupDragAndDrop(item);
      } else {
        // Su mobile, aggiungi supporto touch
        this.setupTouchDragAndDrop(item);
      }
      
      // Click per espandere
      item.addEventListener('click', (e) => {
        if (e.target.closest('.note-remove-btn') || 
            e.target.closest('.note-copy-btn') || 
            e.target.closest('.drag-handle') ||
            e.target.closest('.note-dossier-select')) {
          return;
        }
        
        const preview = item.querySelector('.note-preview');
        const full = item.querySelector('.note-full');
        const isExpanded = item.classList.contains('expanded');
        
        if (isExpanded) {
          preview.style.display = 'block';
          full.style.display = 'none';
          item.classList.remove('expanded');
        } else {
          preview.style.display = 'none';
          full.style.display = 'block';
          item.classList.add('expanded');
        }
      });
    });

    // Setup drop zones
    document.querySelectorAll('.dossier-drop-zone, .notes-drop-zone').forEach(zone => {
      this.setupDropZone(zone);
    });

    // ===== GESTIONE IMMAGINI =====
    
    // Event listeners per pulsanti upload immagini
    document.querySelectorAll('.note-upload-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.showImageUploadDialog(btn.dataset.noteId);
      });
    });

    // Event listeners per thumbnails - visualizza
    document.querySelectorAll('.thumbnail-view').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const thumbnail = btn.closest('.note-image-thumbnail');
        const noteId = thumbnail.dataset.noteId;
        const imageIndex = parseInt(thumbnail.dataset.index);
        const note = this.notes.find(n => n.id === noteId);
        if (note && note.images.length > imageIndex) {
          this.showImageGallery(note.images, imageIndex);
        }
      });
    });

    // Event listeners per thumbnails - rimuovi
    document.querySelectorAll('.thumbnail-remove').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const thumbnail = btn.closest('.note-image-thumbnail');
        const noteId = thumbnail.dataset.noteId;
        const imageId = thumbnail.dataset.imageId;
        this.removeImageFromNote(noteId, imageId);
      });
    });

    // Event listeners per espansione/compressione griglia immagini
    document.querySelectorAll('.images-expand-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const imagesContainer = btn.closest('.note-images');
        const grid = imagesContainer.querySelector('.images-grid');
        const isExpanded = grid.classList.contains('expanded');
        
        if (isExpanded) {
          grid.classList.remove('expanded');
          btn.querySelector('svg').style.transform = 'rotate(0deg)';
          btn.title = 'Espandi';
        } else {
          grid.classList.add('expanded');
          btn.querySelector('svg').style.transform = 'rotate(180deg)';
          btn.title = 'Comprimi';
        }
      });
    });

    // Inizializza tutte le griglie come chiuse
    document.querySelectorAll('.images-grid').forEach(grid => {
      grid.classList.remove('expanded');
    });
    
    // Inizializza tutte le frecce come pointing down
    document.querySelectorAll('.images-expand-btn svg').forEach(svg => {
      svg.style.transform = 'rotate(0deg)';
    });

    // Setup drag & drop per immagini
    this.setupImageDragAndDrop();
  }

  setupDragAndDrop(noteItem) {
    noteItem.addEventListener('dragstart', (e) => {
      e.dataTransfer.setData('text/plain', noteItem.dataset.noteId);
      e.dataTransfer.effectAllowed = 'move';
      noteItem.classList.add('dragging');
    });

    noteItem.addEventListener('dragend', (e) => {
      noteItem.classList.remove('dragging');
    });
  }
  
  setupTouchDragAndDrop(noteItem) {
    let touchItem = null;
    let touchOffset = { x: 0, y: 0 };
    let draggedElement = null;
    
    noteItem.addEventListener('touchstart', (e) => {
      // Solo se tocchi per almeno 500ms inizia il drag
      const touch = e.touches[0];
      touchItem = noteItem;
      
      const rect = noteItem.getBoundingClientRect();
      touchOffset.x = touch.clientX - rect.left;
      touchOffset.y = touch.clientY - rect.top;
      
      // Timer per long press
      touchItem.longPressTimer = setTimeout(() => {
        // Crea elemento clone per il drag
        draggedElement = noteItem.cloneNode(true);
        draggedElement.style.position = 'fixed';
        draggedElement.style.zIndex = '9999';
        draggedElement.style.opacity = '0.8';
        draggedElement.style.pointerEvents = 'none';
        draggedElement.style.width = rect.width + 'px';
        draggedElement.style.transform = 'rotate(2deg) scale(1.05)';
        document.body.appendChild(draggedElement);
        
        noteItem.classList.add('dragging');
        
        // Vibrazione feedback (se supportata)
        if (navigator.vibrate) {
          navigator.vibrate(50);
        }
      }, 500);
    }, { passive: false });
    
    noteItem.addEventListener('touchmove', (e) => {
      if (!draggedElement) {
        // Se non stiamo draggando, cancella il timer
        if (touchItem && touchItem.longPressTimer) {
          clearTimeout(touchItem.longPressTimer);
        }
        return;
      }
      
      e.preventDefault();
      const touch = e.touches[0];
      
      // Muovi l'elemento clone
      draggedElement.style.left = (touch.clientX - touchOffset.x) + 'px';
      draggedElement.style.top = (touch.clientY - touchOffset.y) + 'px';
      
      // Nascondi temporaneamente l'elemento clone per trovare cosa c'è sotto
      draggedElement.style.pointerEvents = 'none';
      draggedElement.style.display = 'none';
      
      // Trova drop zone sotto il dito
      const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
      const dropZone = elementBelow?.closest('.dossier-drop-zone, .notes-drop-zone');
      
      // Rimostra l'elemento clone
      draggedElement.style.display = 'block';
      
      // Rimuovi classe drag-over da tutte le zone
      document.querySelectorAll('.dossier-drop-zone, .notes-drop-zone').forEach(zone => {
        zone.classList.remove('drag-over');
      });
      
      // Aggiungi classe drag-over alla zona attuale
      if (dropZone) {
        dropZone.classList.add('drag-over');
      }
    }, { passive: false });
    
    noteItem.addEventListener('touchend', (e) => {
      // Cancella timer se ancora attivo
      if (touchItem && touchItem.longPressTimer) {
        clearTimeout(touchItem.longPressTimer);
      }
      
      if (!draggedElement) return;
      
      const touch = e.changedTouches[0];
      
      // Nascondi temporaneamente l'elemento clone per trovare la drop zone finale
      draggedElement.style.display = 'none';
      
      // Trova drop zone finale
      const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
      const dropZone = elementBelow?.closest('.dossier-drop-zone, .notes-drop-zone');
      
      // Rimostra l'elemento clone (verrà comunque rimosso dopo)
      draggedElement.style.display = 'block';
      
      if (dropZone) {
        const noteId = noteItem.dataset.noteId;
        const dossierId = dropZone.dataset.dossierId === 'null' ? null : dropZone.dataset.dossierId;
        
        this.moveNoteToDossier(noteId, dossierId);
        this.showMovedFeedback();
      }
      
      // Cleanup
      if (draggedElement) {
        draggedElement.remove();
        draggedElement = null;
      }
      
      noteItem.classList.remove('dragging');
      
      // Rimuovi classe drag-over da tutte le zone
      document.querySelectorAll('.dossier-drop-zone, .notes-drop-zone').forEach(zone => {
        zone.classList.remove('drag-over');
      });
      
      touchItem = null;
    });
    
    // Cancella il drag se l'utente annulla
    noteItem.addEventListener('touchcancel', () => {
      if (touchItem && touchItem.longPressTimer) {
        clearTimeout(touchItem.longPressTimer);
      }
      
      if (draggedElement) {
        draggedElement.remove();
        draggedElement = null;
      }
      
      noteItem.classList.remove('dragging');
      
      document.querySelectorAll('.dossier-drop-zone, .notes-drop-zone').forEach(zone => {
        zone.classList.remove('drag-over');
      });
      
      touchItem = null;
    });
  }

  setupDropZone(dropZone) {
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', (e) => {
      // Solo se stiamo lasciando realmente la drop zone
      if (!dropZone.contains(e.relatedTarget)) {
        dropZone.classList.remove('drag-over');
      }
    });

    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('drag-over');
      
      const noteId = e.dataTransfer.getData('text/plain');
      const rawDossierId = dropZone.dataset.dossierId;
      const dossierId = rawDossierId === 'null' ? null : rawDossierId;
      
      if (noteId) {
        this.moveNoteToDossier(noteId, dossierId);
        this.showMovedFeedback();
      }
    });
  }

  formatNoteContent(content) {
    // Limita la lunghezza del preview
    const maxLength = 150;
    let formatted = content;
    
    // Rimuovi HTML e markdown di base per il preview
    formatted = formatted.replace(/<[^>]*>/g, '');
    formatted = formatted.replace(/[*_`#]/g, '');
    
    if (formatted.length > maxLength) {
      formatted = formatted.substring(0, maxLength) + '...';
    }
    
    return formatted;
  }

  formatFullContent(content) {
    // Mostra il contenuto completo con formattazione base
    let formatted = content;
    
    // Rimuovi solo i tag HTML ma mantieni struttura leggibile
    formatted = formatted.replace(/<[^>]*>/g, '');
    
    // Mantieni i paragrafi
    formatted = formatted.replace(/\n\n/g, '<br><br>');
    formatted = formatted.replace(/\n/g, '<br>');
    
    return formatted;
  }

  async copyNoteToClipboard(content, button) {
    try {
      // Pulisci il contenuto
      const cleanContent = content.replace(/<[^>]*>/g, '');
      
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(cleanContent);
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = cleanContent;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
      }
      
      // Feedback visivo
      const originalText = button.innerHTML;
      button.innerHTML = '✓ Copiato';
      button.classList.add('success');
      
      setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('success');
      }, 2000);
      
    } catch (err) {
      console.error('Errore copia appunto:', err);
    }
  }

  exportNotes() {
    if (this.notes.length === 0) {
      alert('Nessun appunto da esportare');
      return;
    }
    
    // Crea contenuto da esportare
    let content = 'APPUNTI RENTRIA - ' + new Date().toLocaleString('it-IT') + '\n';
    content += '='.repeat(50) + '\n\n';
    
    this.notes.forEach((note, index) => {
      content += `[${index + 1}] ${note.date}\n`;
      content += '-'.repeat(30) + '\n';
      content += note.content.replace(/<[^>]*>/g, '') + '\n\n';
    });
    
    content += '\n' + '='.repeat(50) + '\n';
    content += 'Esportato da PiattaformaRentriFacile.it';
    
    // Crea blob e scarica
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'appunti-rentria-' + new Date().toISOString().split('T')[0] + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    this.showExportedFeedback();
  }

  togglePanel() {
    if (this.isOpen) {
      this.closePanel();
    } else {
      this.openPanel();
    }
  }

  openPanel() {
    const notesBtn = document.getElementById('notes-btn');
    
    // FORCE REFRESH COMPLETO - ricrea tutto da zero
    this.forceRefresh();
    
    // Ora il pannello è stato ricreato, prendiamo il riferimento aggiornato
    const panel = document.getElementById('notes-panel');
    
    if (panel) {
      panel.classList.remove('closed');
      panel.classList.add('open');
      this.isOpen = true;
      
      // Evidenzia il pulsante Note
      if (notesBtn) {
        notesBtn.classList.add('active');
      }
      
      // Anima l'apertura
      setTimeout(() => {
        panel.style.transform = 'translateX(0)';
      }, 10);
    }
  }

  closePanel() {
    const panel = document.getElementById('notes-panel');
    const notesBtn = document.getElementById('notes-btn');
    
    if (panel) {
      panel.classList.remove('open');
      panel.classList.add('closed');
      this.isOpen = false;
      
      // Rimuovi evidenziazione dal pulsante Note
      if (notesBtn) {
        notesBtn.classList.remove('active');
      }
      
      // Forza il transform per la chiusura
      setTimeout(() => {
        panel.style.transform = 'translateX(100%)';
      }, 10);
    }
  }

  updateBadge() {
    // Numero nel pulsante Note
    const countDisplay = document.getElementById('notes-count-display');
    const count = document.querySelector('.notes-count');
    
    if (countDisplay) {
      countDisplay.textContent = this.notes.length;
      // Cambia colore se ci sono note
      if (this.notes.length > 0) {
        countDisplay.style.color = '#1F7138'; // Verde per evidenziare
      } else {
        countDisplay.style.color = '#999'; // Grigio quando 0
      }
    }
    
    if (count) {
      const noteText = this.notes.length + (this.notes.length === 1 ? ' nota' : ' note');
      const dossierText = this.dossiers.length + (this.dossiers.length === 1 ? ' dossier' : ' dossier');
      count.textContent = `${noteText}, ${dossierText}`;
    }
  }

  showMovedFeedback() {
    const toast = document.createElement('div');
    toast.className = 'notes-toast success';
    toast.innerHTML = '↔️ Nota spostata';
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  showAddedFeedback() {
    // Crea toast notification
    const toast = document.createElement('div');
    toast.className = 'notes-toast success';
    toast.innerHTML = '✓ Aggiunto agli appunti';
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  showClearedFeedback() {
    const toast = document.createElement('div');
    toast.className = 'notes-toast info';
    toast.innerHTML = '🗑️ Appunti cancellati';
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  showExportedFeedback() {
    const toast = document.createElement('div');
    toast.className = 'notes-toast success';
    toast.innerHTML = '📥 Appunti esportati';
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  saveNotes() {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(this.notes));
    } catch (err) {
      console.error('Errore salvataggio appunti:', err);
    }
  }

  loadNotes() {
    try {
      const saved = localStorage.getItem(this.storageKey);
      if (saved) {
        this.notes = JSON.parse(saved);
        // Valida le note caricate e migra struttura dati
        this.notes = this.notes.filter(n => n && n.id && n.content).map(note => ({
          ...note,
          dossierId: note.dossierId || null,
          images: note.images || [] // Migrazione: aggiungi campo images se mancante
        }));
      }
    } catch (err) {
      console.error('Errore caricamento appunti:', err);
      this.notes = [];
    }
  }

  loadDossiers() {
    try {
      const saved = localStorage.getItem(this.dossiersStorageKey);
      if (saved) {
        this.dossiers = JSON.parse(saved);
        // Valida i dossier caricati
        this.dossiers = this.dossiers.filter(d => d && d.id && d.name);
      }
    } catch (err) {
      console.error('Errore caricamento dossier:', err);
      this.dossiers = [];
    }
  }

  saveDossiers() {
    try {
      localStorage.setItem(this.dossiersStorageKey, JSON.stringify(this.dossiers));
    } catch (err) {
      console.error('Errore salvataggio dossier:', err);
    }
  }

  // ===============================
  // GESTIONE IMMAGINI
  // ===============================

  /**
   * Aggiunge immagini a una nota esistente
   * @param {string} noteId - ID della nota
   * @param {FileList} files - File immagini da aggiungere
   */
  async addImagesToNote(noteId, files) {
    const note = this.notes.find(n => n.id === noteId);
    if (!note) {
      console.error('Nota non trovata:', noteId);
      return;
    }

    // Verifica limite immagini per nota
    if (note.images.length + files.length > this.maxImagesPerNote) {
      alert(`Massimo ${this.maxImagesPerNote} immagini per nota. Attualmente: ${note.images.length}`);
      return;
    }

    // Mostra progress indicator
    this.showImageUploadProgress(noteId, files.length);

    let processed = 0;
    const errors = [];

    for (let file of files) {
      try {
        const compressedImage = await this.imageCompressor.compressImage(file);
        note.images.push(compressedImage);
        processed++;
        
        // Aggiorna progress
        this.updateImageUploadProgress(noteId, processed, files.length);
        
      } catch (error) {
        console.error('Errore compressione immagine:', error);
        errors.push(`${file.name}: ${error.message}`);
      }
    }

    // Salva modifiche
    this.saveNotes();
    
    // Aggiorna display
    this.updateNotesDisplay();
    
    // Nascondi progress e mostra risultato
    this.hideImageUploadProgress(noteId);
    
    if (errors.length > 0) {
      alert(`Alcune immagini non sono state caricate:\n${errors.join('\n')}`);
    } else if (processed > 0) {
      this.showToast(`${processed} immagine/i aggiunte con successo!`, 'success');
    }
  }

  /**
   * Rimuove un'immagine da una nota
   * @param {string} noteId - ID della nota
   * @param {string} imageId - ID dell'immagine da rimuovere
   */
  removeImageFromNote(noteId, imageId) {
    const note = this.notes.find(n => n.id === noteId);
    if (!note) return;

    const imageIndex = note.images.findIndex(img => img.id === imageId);
    if (imageIndex === -1) return;

    // Conferma rimozione
    if (confirm('Rimuovere questa immagine dalla nota?')) {
      note.images.splice(imageIndex, 1);
      this.saveNotes();
      this.updateNotesDisplay();
      this.showToast('Immagine rimossa', 'info');
    }
  }

  /**
   * Mostra progress indicator per upload immagini
   * @param {string} noteId - ID della nota
   * @param {number} totalFiles - Numero totale di file
   */
  showImageUploadProgress(noteId, totalFiles) {
    const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
    if (!noteElement) return;

    const progressContainer = document.createElement('div');
    progressContainer.className = 'image-upload-progress';
    progressContainer.innerHTML = `
      <div class="progress-info">
        <span>Caricamento immagini...</span>
        <span class="progress-count">0 / ${totalFiles}</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width: 0%"></div>
      </div>
    `;
    
    noteElement.appendChild(progressContainer);
  }

  /**
   * Aggiorna progress indicator
   * @param {string} noteId - ID della nota
   * @param {number} processed - Numero file processati
   * @param {number} total - Numero totale file
   */
  updateImageUploadProgress(noteId, processed, total) {
    const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
    const progressContainer = noteElement?.querySelector('.image-upload-progress');
    if (!progressContainer) return;

    const percentage = (processed / total) * 100;
    
    const countElement = progressContainer.querySelector('.progress-count');
    const fillElement = progressContainer.querySelector('.progress-fill');
    
    if (countElement) countElement.textContent = `${processed} / ${total}`;
    if (fillElement) fillElement.style.width = `${percentage}%`;
  }

  /**
   * Nasconde progress indicator
   * @param {string} noteId - ID della nota
   */
  hideImageUploadProgress(noteId) {
    const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
    const progressContainer = noteElement?.querySelector('.image-upload-progress');
    if (progressContainer) {
      setTimeout(() => progressContainer.remove(), 500);
    }
  }

  /**
   * Crea gallery viewer per visualizzare immagini full-size
   * @param {Array} images - Array di immagini
   * @param {number} startIndex - Indice immagine iniziale
   */
  showImageGallery(images, startIndex = 0) {
    const modal = document.createElement('div');
    modal.className = 'image-gallery-modal';
    modal.innerHTML = `
      <div class="gallery-backdrop" onclick="this.parentElement.remove()"></div>
      <div class="gallery-container">
        <button class="gallery-close" onclick="this.closest('.image-gallery-modal').remove()">×</button>
        <div class="gallery-content">
          <button class="gallery-nav prev" ${startIndex === 0 ? 'disabled' : ''}>‹</button>
          <div class="gallery-image-container">
            <img class="gallery-image" src="${images[startIndex].dataUrl}" alt="${images[startIndex].fileName}">
          </div>
          <button class="gallery-nav next" ${startIndex === images.length - 1 ? 'disabled' : ''}>›</button>
        </div>
        <div class="gallery-info">
          <span class="gallery-filename">${images[startIndex].fileName}</span>
          <span class="gallery-counter">${startIndex + 1} / ${images.length}</span>
          <span class="gallery-size">${images[startIndex].compressedSize.width} × ${images[startIndex].compressedSize.height}</span>
        </div>
      </div>
    `;

    // Gestione navigazione
    let currentIndex = startIndex;
    const updateGallery = (index) => {
      const img = modal.querySelector('.gallery-image');
      const filename = modal.querySelector('.gallery-filename');
      const counter = modal.querySelector('.gallery-counter');
      const size = modal.querySelector('.gallery-size');
      const prevBtn = modal.querySelector('.prev');
      const nextBtn = modal.querySelector('.next');

      img.src = images[index].dataUrl;
      img.alt = images[index].fileName;
      filename.textContent = images[index].fileName;
      counter.textContent = `${index + 1} / ${images.length}`;
      size.textContent = `${images[index].compressedSize.width} × ${images[index].compressedSize.height}`;
      
      prevBtn.disabled = index === 0;
      nextBtn.disabled = index === images.length - 1;
    };

    modal.querySelector('.prev').addEventListener('click', () => {
      if (currentIndex > 0) {
        currentIndex--;
        updateGallery(currentIndex);
      }
    });

    modal.querySelector('.next').addEventListener('click', () => {
      if (currentIndex < images.length - 1) {
        currentIndex++;
        updateGallery(currentIndex);
      }
    });

    // Gestione keyboard
    const handleKeydown = (e) => {
      if (e.key === 'Escape') modal.remove();
      if (e.key === 'ArrowLeft' && currentIndex > 0) {
        currentIndex--;
        updateGallery(currentIndex);
      }
      if (e.key === 'ArrowRight' && currentIndex < images.length - 1) {
        currentIndex++;
        updateGallery(currentIndex);
      }
    };

    document.addEventListener('keydown', handleKeydown);
    modal.addEventListener('remove', () => {
      document.removeEventListener('keydown', handleKeydown);
    });

    document.body.appendChild(modal);
  }

  /**
   * Mostra dialog per upload immagini
   * @param {string} noteId - ID della nota
   */
  showImageUploadDialog(noteId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/jpg,image/webp,image/gif';
    input.multiple = true;
    
    input.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        this.addImagesToNote(noteId, e.target.files);
      }
    });
    
    input.click();
  }

  /**
   * Setup drag and drop per immagini
   */
  setupImageDragAndDrop() {
    // Setup drop zones per immagini
    document.querySelectorAll('.add-image-drop-zone').forEach(zone => {
      const noteId = zone.dataset.noteId;
      
      zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('drag-over');
      });
      
      zone.addEventListener('dragleave', (e) => {
        if (!zone.contains(e.relatedTarget)) {
          zone.classList.remove('drag-over');
        }
      });
      
      zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        
        const files = Array.from(e.dataTransfer.files).filter(file => 
          this.imageCompressor.isValidImage(file)
        );
        
        if (files.length > 0) {
          this.addImagesToNote(noteId, files);
        } else if (e.dataTransfer.files.length > 0) {
          alert('Solo immagini PNG, JPEG, WebP e GIF sono supportate.');
        }
      });
      
      zone.addEventListener('click', () => {
        this.showImageUploadDialog(noteId);
      });
    });
  }

  /**
   * Mostra toast notification
   * @param {string} message - Messaggio da mostrare
   * @param {string} type - Tipo di toast (success, error, info)
   */
  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `notes-toast ${type}`;
    toast.textContent = message;
    
    // Stile del toast
    Object.assign(toast.style, {
      position: 'fixed',
      top: '20px',
      right: '20px',
      padding: '12px 16px',
      borderRadius: '6px',
      color: '#fff',
      fontWeight: '500',
      fontSize: '14px',
      zIndex: '10000',
      transform: 'translateX(100%)',
      transition: 'transform 0.3s ease'
    });
    
    // Colori per tipo
    const colors = {
      success: '#28a745',
      error: '#dc3545',
      info: '#0C2267'
    };
    toast.style.backgroundColor = colors[type] || colors.info;
    
    document.body.appendChild(toast);
    
    // Anima entrata
    setTimeout(() => {
      toast.style.transform = 'translateX(0)';
    }, 10);
    
    // Rimuovi dopo 3 secondi
    setTimeout(() => {
      toast.style.transform = 'translateX(100%)';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
}

// Funzione helper per aggiungere il pulsante appunti ai messaggi
function addNotesToButton(messageElement, messageId) {
  // Verifica che sia un messaggio del bot
  if (!messageElement.classList.contains('bot')) return;
  
  // Verifica che non ci sia già il pulsante
  if (messageElement.querySelector('.add-to-notes-btn')) return;
  
  // Trova o crea il container dei pulsanti
  let btnContainer = messageElement.parentElement?.querySelector('.action-btn-container');
  
  if (!btnContainer) {
    // Se non c'è wrapper, crealo
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
      wrapper = document.createElement('div');
      wrapper.className = 'msg-wrapper';
      messageElement.parentNode.insertBefore(wrapper, messageElement);
      wrapper.appendChild(messageElement);
    }
    
    btnContainer = document.createElement('div');
    btnContainer.className = 'action-btn-container';
    wrapper.appendChild(btnContainer);
  }
  
  // Crea il pulsante appunti
  const notesBtn = document.createElement('button');
  notesBtn.className = 'action-btn add-to-notes-btn';
  notesBtn.setAttribute('data-message-id', messageId);
  notesBtn.innerHTML = `
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
      <polyline points="14,2 14,8 20,8"/>
      <line x1="16" y1="13" x2="8" y2="13"/>
      <line x1="16" y1="17" x2="8" y2="17"/>
      <polyline points="10,9 9,9 8,9"/>
    </svg>
  `;
  
  // Event listener
  notesBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    console.log('Pulsante appunti cliccato');
    
    // Ottieni il contenuto del messaggio - proviamo diversi metodi
    let content = '';
    
    // Metodo 1: Cerca contenuto pulito senza pulsanti
    const msgContent = messageElement.querySelector('.msg-content');
    if (msgContent) {
      content = msgContent.textContent || msgContent.innerText;
    } else {
      // Metodo 2: Clona e rimuovi i pulsanti
      const clone = messageElement.cloneNode(true);
      const buttons = clone.querySelectorAll('.action-btn-container, .action-btn, .copy-btn, .whatsapp-btn, .telegram-btn, .add-to-notes-btn');
      buttons.forEach(btn => btn.remove());
      content = clone.textContent || clone.innerText;
    }
    
    content = content.trim();
    console.log('Contenuto da salvare:', content);
    
    // Aggiungi agli appunti
    if (window.notesManager && content) {
      window.notesManager.addNote(content, messageId);
      
      // Feedback visivo sul pulsante
      const originalHTML = notesBtn.innerHTML;
      notesBtn.classList.add('success');
      notesBtn.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      `;
      
      setTimeout(() => {
        notesBtn.classList.remove('success');
        notesBtn.innerHTML = originalHTML;
      }, 2000);
    } else {
      console.error('NotesManager non disponibile o contenuto vuoto');
    }
  });
  
  // Aggiungi il pulsante al container
  btnContainer.appendChild(notesBtn);
}

// Inizializza quando il DOM è pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.notesManager = new NotesManager();
    console.log('NotesManager inizializzato');
  });
} else {
  window.notesManager = new NotesManager();
  console.log('NotesManager inizializzato (DOM già pronto)');
}

// Esporta le funzioni per uso esterno
window.addNotesToButton = addNotesToButton;/* Updated Wed Aug 20 09:21:34 UTC 2025 */
