/**
 * Liturgus Frontend JS
 */

function liturgusSignup(messeId, slotKey, isBackup) {
    if (!confirm('Möchten Sie sich wirklich eintragen?')) {
        return;
    }
    
    const data = new FormData();
    data.append('action', 'liturgus_signup');
    data.append('nonce', liturgusData.nonce);
    data.append('messe_id', messeId);
    data.append('slot_key', slotKey);
    if (isBackup) {
        data.append('is_backup', '1');
    }
    
    fetch(liturgusData.ajaxUrl, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(result.data.message);
            location.reload();
        } else {
            alert(result.data.message || 'Fehler beim Eintragen');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Fehler beim Eintragen');
    });
}

function liturgusCancel(assignmentId) {
    if (!confirm('Wirklich austragen?')) {
        return;
    }
    
    const data = new FormData();
    data.append('action', 'liturgus_cancel');
    data.append('nonce', liturgusData.nonce);
    data.append('assignment_id', assignmentId);
    
    fetch(liturgusData.ajaxUrl, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(result.data.message);
            location.reload();
        } else {
            alert(result.data.message || 'Fehler beim Austragen');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Fehler beim Austragen');
    });
}

function liturgusOpenSwap(assignmentId, slotLabel, datetime, messTitle) {
    document.getElementById('liturgus-swap-my-assignment-id').value = assignmentId;
    document.getElementById('liturgus-swap-my-service').innerHTML = 
        '<strong>' + slotLabel + '</strong><br>' + 
        datetime + '<br>' + 
        messTitle;
    document.getElementById('liturgus-swap-modal').style.display = 'flex';
}

function liturgusCloseSwap() {
    document.getElementById('liturgus-swap-modal').style.display = 'none';
    document.getElementById('liturgus-swap-target').value = '';
    document.getElementById('liturgus-swap-message').value = '';
}

function liturgusSubmitSwap() {
    const myAssignmentId = document.getElementById('liturgus-swap-my-assignment-id').value;
    const theirAssignmentId = document.getElementById('liturgus-swap-target').value;
    const message = document.getElementById('liturgus-swap-message').value;
    
    if (!theirAssignmentId) {
        alert('Bitte Dienst auswählen');
        return;
    }
    
    if (!confirm('Wirklich tauschen? Beide Dienste werden getauscht!')) {
        return;
    }
    
    const data = new FormData();
    data.append('action', 'liturgus_swap_request');
    data.append('nonce', liturgusData.nonce);
    data.append('my_assignment_id', myAssignmentId);
    data.append('their_assignment_id', theirAssignmentId);
    data.append('message', message);
    
    fetch(liturgusData.ajaxUrl, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(result.data.message);
            liturgusCloseSwap();
            location.reload();
        } else {
            alert(result.data.message || 'Fehler beim Senden');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Fehler beim Senden');
    });
}

// ESC zum Schließen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('liturgus-swap-modal');
        if (modal && modal.style.display === 'flex') {
            liturgusCloseSwap();
        }
    }
});
