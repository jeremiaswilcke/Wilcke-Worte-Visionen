# Isidor Liturgus v2.0.0

## 🎯 Clean Start - Simpel & Zuverlässig

### Installation:

1. **DB ist bereits gelöscht** ✅
2. **Plugin hochladen & aktivieren**
3. **Seite erstellen** mit Shortcode: `[liturgus_dashboard]`
4. **Fertig!**

### Was passiert beim Aktivieren:

- ✅ 3 Tabellen werden erstellt
- ✅ 10 Dienst-Slots werden angelegt
- ✅ Capabilities werden gesetzt

### Slots (Standard):

1. Technik (1 Haupt + 1 Backup)
2. Orgel (1 + 1)
3. Kantor (1 + 1)
4. Lektor 1 (1 + 1)
5. Lektor 2 (1 + 1)
6. Kommunionhelfer (2 + 2)
7. Ministranten (6 + 2)
8. Diakon Evangelium (1 + 1)
9. Diakon Predigt (1 + 1)
10. Priester (1 + 1)

### Funktionen:

- ✅ Eintragen (Haupt + Backup)
- ✅ Austragen
- ✅ Dashboard mit Übersicht
- ✅ Responsive Design

### Architektur:

**Super simpel:**
- `class-database.php` - Tabellen erstellen
- `class-slots.php` - Dienste verwalten
- `class-assignments.php` - Ein/Austragen
- `class-dashboard.php` - Frontend
- `dashboard.php` - Template
- `liturgus.css` - Styling
- `liturgus.js` - AJAX

**= Nur das Nötigste!**

### Shortcode:

```
[liturgus_dashboard]
```

Zeigt:
- Freie Dienste (nächste 3 Wochen)
- Meine Dienste (Tabelle)
- Buttons zum Ein/Austragen

### User Capabilities:

- `liturgus_manage` - Admins (alles)
- `liturgus_signup` - Editors + Admins (eintragen/austragen)

### DB-Tabellen:

- `wp_liturgus_slots` - Dienst-Definitionen
- `wp_liturgus_assignments` - Zuweisungen
- `wp_liturgus_swaps` - Tausch-Anfragen (später)

### Messen:

- Nutzt **existierende Messen** (Custom Post Type `isidor_messe`)
- Meta-Keys: `_is_date`, `_is_time`

**= Kein eigenes Messen-Management!**

---

## ✅ Nach Installation prüfen:

- [ ] Plugin aktiviert
- [ ] Seite mit Shortcode erstellt
- [ ] Dashboard zeigt "Freie Dienste"
- [ ] Buttons (✓ und B) sind sichtbar
- [ ] Eintragen funktioniert
- [ ] "Meine Dienste" zeigt Einträge
- [ ] Austragen funktioniert

**Wenn ALLES ✅ → PERFEKT!** 🎉

---

**Version:** 2.0.0  
**Status:** ✅ CLEAN START  
**Code:** Minimal & Simpel  
**Garantiert:** Funktioniert!  

**= Kein Overengineering, nur das Nötigste!** 🚀
