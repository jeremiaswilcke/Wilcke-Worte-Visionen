# Isidor Liturgus

Dienstplanung für Gottesdienste - Teil des Isidor Core OS Ökosystems.

## 🎯 Professionelles Dienst-Management für Gottesdienste

Liturgus ist ein WordPress-Plugin zur Verwaltung von liturgischen Diensten mit intelligenter Tauschfunktion, automatischen Erinnerungen und Backend-Zuweisung.

---

## 📦 Installation

1. **Plugin hochladen** nach `/wp-content/plugins/isidor-liturgus/`
2. **Plugin aktivieren**
3. **Im Admin unter "Liturgus"** auf "Dashboard-Seite jetzt erstellen" klicken

Alternativ manuell eine Seite erstellen mit dem Shortcode `[liturgus_dashboard]`.

---

## ✨ Features v2.5.3

### **Frontend (User):**
- ✅ Eintragen als Haupt- oder Ersatzdienst
- ✅ Austragen (Backup rückt automatisch nach!)
- ✅ Intelligente Tausch-Funktion (echtes Tauschen beider Dienste)
- ✅ iCal-Export der eigenen Dienste
- ✅ Namen-Anzeige (wer ist eingetragen)
- ✅ Responsive Design

### **Backend (Admin/Pfarrer/Diakon/Sekretär):**
- ✅ Dienste per Dropdown zuweisen
- ✅ Haupt- und Ersatzdienste festlegen
- ✅ Batch-Verwaltung nach Zeitraum
- ✅ **NEU:** Ein-Klick-Erstellung der Dashboard-Seite

### **Email-System:**
- ✅ Bei Zuweisung durch Admin
- ✅ Bei eigenem Eintragen
- ✅ Bei Austragen
- ✅ Bei Backup-Nachrücken
- ✅ Bei Tausch-Anfrage (mit Annahme-Link!)
- ✅ Weekly Reminder (Montag 9:00 Uhr - unbesetzte Dienste)
- ✅ Evening Reminder (Täglich 18:00 Uhr - Dienst am nächsten Tag)

### **Tausch-System:**
- ✅ Nur mit Usern tauschen, die auch Dienste haben
- ✅ Klare Email: "DU GIBST AB" / "DU BEKOMMST"
- ✅ Ein-Klick-Annahme aus Email
- ✅ Token-gesichert
- ✅ Echter Tausch: Beide Dienste werden gewechselt

---

## 🎭 Rollen & Berechtigungen

### **`liturgus_signup`** - Kann sich eintragen/austragen:
- Administrator, Editor
- isidor_pfarrer, isidor_diakon, isidor_sekretaer
- isidor_lektor, isidor_kommunion, isidor_ministrant
- isidor_orgel, isidor_technik

### **`liturgus_assign_others`** - Kann andere zuweisen:
- Administrator, isidor_pfarrer, isidor_diakon, isidor_sekretaer

---

## 🔄 Shortcode

```
[liturgus_dashboard]
```

Zeigt das komplette Dashboard für eingeloggte User.

---

## ⚙️ WordPress Cron

### **Automatic Reminders:**
- Montag 9:00: Unbesetzte Dienste (nächste 7-14 Tage)
- Täglich 18:00: Dienste morgen

### **Empfehlung bei wenig Traffic:**
Server-Cron aktivieren:
```bash
*/15 * * * * wget -q -O - https://ihre-domain.at/wp-cron.php?doing_wp_cron
```

---

## 🔄 Updates via GitHub

Dieses Plugin unterstützt automatische Updates über GitHub. Bei aktiviertem GitHub Updater Plugin werden neue Versionen automatisch erkannt.

---

## 📊 Changelog

### v2.5.3
- **Fix**: Dashboard-URL wird automatisch erkannt (keine hardcoded `/dienste/` URL mehr)
- **Neu**: Ein-Klick-Erstellung der Dashboard-Seite im Admin
- **Neu**: URL-Caching für bessere Performance
- **Fix**: Tausch-Annahme-Links führen nicht mehr zu 404

### v2.5.2
- Tausch-System mit Email-Bestätigung
- Backup-Nachrückung bei Austragung
- Verbesserte Email-Formatierung

### v2.5.1
- Frontend mit Dienst-Dropdown, Bestätigung

### v2.5.0
- Echter Tausch beider Dienste, klare Emails

### v2.4.0
- Backup nachrücken, Email-Annahme

---

## Abhängigkeiten

- **Isidor Core** (für Messen Post-Type)
- WordPress 5.8+
- PHP 7.4+

---

**Version:** 2.5.3  
**Status:** ✅ Production Ready  
**Teil der Isidor-Suite**

**= Professionelles Dienst-Management!** ❤️🎵
