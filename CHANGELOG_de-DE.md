# 3.1.1
* Fix: Fehlerhafte suche bei konfigurierter Produktnummer als Nosto ID in ther Pluginkonfiguration
* Fix: Fehler im Produktsynchronisierung bei Produkten im Ausverkauf
* Fix: Fehler bei Produkten mit der Storefront-Präsentation "Hauptprodukt" für Produkte mit Varianten

# 3.1.0
* Neu: Support für mehr Optionen in der Storefront-Darstellung

# 3.0.0
* Beachten Sie, dass diese Version nur Shop-Versionen ab v6.5.4 unterstützt 
* Funktion: Unterstützung der nativen Nosto Search und Category Merchandising 2 
* Funktion: Möglichkeit, sprachspezifische Plugin-Konfigurationen hinzuzufügen 
* Funktion: Konfiguration zum Ausschließen von Produkten innerhalb spezifischer Kategorien 
* Funktion: Die Produktsynchronisierung berücksichtigt nun die Storefront-Präsentation jedes Produkts 
* Änderung: Wechsel zu einer OpenSource-Lizenz

# 2.5.1
* Fix: Einige Formulierungen/Tippfehler behoben
* Fix: Es wurde ein Problem behoben, das bei einigen Kunden auftreten konnte, nachdem sie die Shop-Sprache geändert hatten.
* Fix: Die vollständige Katalogsynchronisierung und die geplante Synchronisierung erfordern jetzt möglicherweise mehr als einen Worker.
* Fix: Es wurde ein Problem behoben, bei dem das Markenbild vom Nosto-Crawler entfernt werden konnte (aber über API/Sync hinzugefügt wurde).
* Fix: Es wurde ein Problem behoben, bei dem Produktbilder nicht mit der Reihenfolge übereinstimmten, in der sie sich in Shopware befinden.

# 2.5.0
* Neu: Konfigurationsoption auf der Plugin-Konfigurationsseite hinzugefügt, mit der nun in Tagen angegeben werden kann, wie lange alte verarbeitete geplante Jobs (im Nosto-Plugin geschulte Jobs) gespeichert werden sollen.
* Fix: Ein Problem mit Varianten der Produktausgabe in Produktempfehlungen vs. Merchandising wurde behoben
* Fix: Verbesserte Leistung des Synchronisierungsvorgangs für den vollständigen Katalog (Schaltfläche „Synchronisierung des vollständigen Katalogs“ auf der Nosto Grid-Seite im Adminpanel). Dies sollte das Problem für Kunden lösen, die große Mengen an Produkten auf ihrer Website haben und das Problem haben, dass „nicht alle Produkte im Nosto-Admin-Bereich angezeigt werden“.

# 2.4.3
* Fix: Es wurde ein Problem behoben, bei dem neu hinzugefügte Produkte (zu dynamischen Gruppen oder manuell) nicht im Store angezeigt wurden.
* Fix: Produktherstellerdaten, die an Nosto gesendet werden, enthalten jetzt eine Variable „brand-image-url", die in Nosto-Vorlagen verwendet werden kann, wenn das Bild verfügbar ist.

# 2.4.2
* Fix: Div-Klasse Nosto-Integration-Block entfernt, um die Nostoelemente zu wickeln

# 2.4.1
* Fix: Das Problem wurde behoben, bei dem Analyse-Gesamtdaten im Nosto-Dashboard fehlerhaft verfolgt wurden
* Fix: Es wurde ein Problem behoben, das dazu führte, dass der Nosto-Crawler Produkte sporadisch einstellte

# 2.4.0
* Feature: Feature-Unterstützung für „Produkte nach Ausverkauf ausblenden“ hinzugefügt
* Fix: Problem behoben, bei dem Produkte vor Ort auf Lager waren, in Nosto jedoch OOS

# 2.3.1

* Fix: Logik zur „Vollständigen Katalogsynchronisierung“ hinzugefügt, die Probleme mit Produktvarianten löst (Produkte werden basierend auf der Darstellung der Produktvariantenkonfiguration im Laden rabattiert)
* Fix: Ein Problem, bei dem einige Produkte in Nosto Merchandising und Katalog eingestellt wurden.
* Fix: Die Produkte werden nicht vertauscht, nachdem die Positionen der Produkte geändert wurden
* Fix: Das Problem, wenn Produkt-Tags/benutzerdefinierte Felder nicht synchronisiert werden können

# 2.3.0

* Fix: Das Problem beim Zuweisen einer dynamischen Gruppe von Merchandising-Produkten zu Kategorien wurde behoben.
* Neu: Funktionalität für Buchhaltungskategorien mit dynamischen Produktgruppen hinzugefügt.
* Neu: Es wurde eine neue GraphQL-API zum Sammeln der Kategorienliste hinzugefügt, um unser Category Merchandising-Produkt besser zu unterstützen.

# 2.2.1

* Behebung: Das Problem wurde behoben, das bei einigen Kunden auftreten konnte, wenn die Nosto-Empfehlungs-/Merchandising-Seite abstürzte, wenn die Produktkennung auf „Produktnummer“ eingestellt war.

# 2.2.0

* Besonderheit: Die Nosto-Cookies sind First-Party-Cookies. Das Plugin setzt die Cookies als unbedingt erforderlich und lädt sie immer, anstatt sie als etwas zu bewerten und sie optional nach der Auswahl des Benutzers zu laden

# 2.1.0

* Hinzugefügt: ID jeder Produktkategorie zu einem Produktdatenobjekt hinzugefügt, bevor es an den Nosto-Service gesendet wird.
* Fix: Das Problem wurde behoben, wenn der Schalter „Variationen aktivieren" keinen Einfluss auf die Produktdatenstruktur hatte, die an den Nosto-Dienst gesendet wurde.

# 2.0.2

* Fix: Das Problem wurde behoben, wenn der Empfehlungsfilter für einige Benutzer nicht wie vorgesehen funktionierte.
* Fix: Das Problem wurde behoben, bei dem Nosto-Widgets auf der Seite, auf der sie hinzugefügt wurden, Fehler verursachen konnten.

# 2.0.1

* Fix: Das Problem mit Konfigurationsbenennungen wurde behoben.

# 2.0.0

* Kompatibilitätsfreigabe mit Shopwrae 6.5^
* Fix: Verwendung entfernter Klassen und Dateien ersetzt.
* Fix: Das Problem wurde behoben, das bei einigen Plugin-Benutzern während der Datensynchronisierung über das Shopware-Admin-Panel auftrat.
* Fix: Kleinere Änderungen an Erweiterungskonfigurationsklassen/-vorlagen (auf der Erweiterungskonfigurationsseite).
* Neu: Job Scheduler Update – Kompatibilität mit Shopawre 6.5^-Versionen implementiert.
* Neu: Job-Scheduler-Update – Job-Scheduler-Handler erweitern jetzt empfohlene Schnittstellen.
* Neu: Controller-Routen verfügen jetzt über eine Annotationsdeklaration im neuen Format.
* Neu: Einige vorgenommene Änderungen machen die Erweiterung abwärtsinkompatibel. Sie können die Abhängigkeiten in der Datei „composer.json“ sehen.

# 1.0.18

* Der Fehler wurde behoben, indem Kriterien zur Nosto-Sortiermethode hinzugefügt wurden

# 1.0.17

* Kleinere Fehlerbehebungen: Es wurde ein Problem behoben, bei dem Website-Besucher auf der Storefront auf einen Fehler stoßen konnten, nachdem sie den Checkout erreicht und zur vorherigen Seite zurückgekehrt waren.

# 1.0.16

* Unterstützung für die Funktionalität „Warenkorb wiederherstellen“/„Warenkorb verlassen“ hinzugefügt. Jetzt erhält der Nosto-Dienst neben allen anderen Kartendaten auch den Link „restore_cart“. 

# 1.0.15

* Fix: Problem behoben, das bei einigen Benutzern bei der „vollständigen Produktsynchronisierung“ auftreten kann. Fehlermeldung: Countable|array int bereitgestellt
* Fix: Doppelter Text für die Tooltip-Beschreibung der Nosto-Konfigurationsoption (Adminpanel)

# 1.0.14

* Neu: Hinzufügen der Auswahl des Nosto-Produktidentifikators
* Neu: Hinzufügen aller Informationen im Zusammenhang mit Cross-Selling

# 1.0.13

* Fix: Ladevorgang von ProductCloseoutFilter für ältere Versionen korrigiert
* Fix: Produkt-Hauptvarianten-Konfigurationslader für ältere Version entfernt

# 1.0.12

* Neu: Hauptproduktinformation hinzugefügt

# 1.0.11

* Fix: Problem der Tag-Ladebeschränkung behoben

# 1.0.10

* Neu: Tag-Auswahl von Tag-Werten anstelle von benutzerdefinierten Feldern hinzugefügt

# 1.0.9

* Neu: Hinzufügen von Produktbeschriftungen zu den benutzerdefinierten Feldern von Nosto Product
* Neu: Produktnummer zu den benutzerdefinierten Feldern von Nosto Product hinzugefügt

# 1.0.8

* Neu: Nosto js Objekt auf CMS Seiten mit addSkuToCart, addProductToCart, addMultipleProductsToCart Methoden hinzugefügt

# 1.0.7

* Neu: Cross-Selling-Synchronisation hinzugefügt
* Fix: Berechnung des festen Bruttopreises für Nosto-Produkte

# 1.0.6

* Neu: Inventarauswahl in der Nosto-Konfiguration hinzugefügt
* Fix: Nosto js Problem auf der Kassenseite behoben

# 1.0.5

* Neu: Recommended Sortieroption für Merchandising hinzugefügt
* Fix: Speicherung und Validierung der Nosto-Konfiguration behoben

# 1.0.4

* Neu: Kompatibilität mit benutzerdefinierten Produktseiten hinzugefügt
* Neu: Kompatibilität mit nicht-skalaren benutzerdefinierten Feldern hinzugefügt
* Neu: Domainauswahl für Multidomainshops hinzugefügt
* Fix: Problem mit dem Kategorie Merchandiser Konto behoben

# 1.0.3

* Fix: Merchandiser der festen Kategorie

# 1.0.2

* Neu: Kompatibilität mit benutzerdefinierten Designs hinzugefügt
* Neu: Erforderliche Felder werden als nicht erforderlich markiert, wenn das Konto nicht aktiviert ist
* Fix: Kontext wird für Hintergrundprozesse beibehalten
* Fix: Alle Daten während der Deinstallation entfernt
* Fix: Serverseitig generierte Cookies im Zusammenhang mit den Berechtigungen behoben
* Fix: Serverseitig generierte Cookies im Zusammenhang mit den Berechtigungen behoben
* Fix: Fälle von leeren Kategorien, Produktbildern und Produkt-URLs behandelt
* Fix: CSS entfernte "wichtige" Schlüsselwörter
* Fix: UI für Nosto CMS-Element korrigiert

# 1.0.1

* Neu: API-Schlüsselvalidierung in Nosto-Konfiguration hinzugefügt
* Neu: Cookie-Berechtigungen für Nosto-Tracking hinzugefügt
* Neu: Kompatibilität mit den neuesten Versionen von Shopware hinzugefügt
* Neu: Übersetzungen für das gesamte Modul hinzugefügt
* Fix: Schlüssel für benutzerdefinierte Felder in Nosto-Konfiguration in Beschriftung geändert

# 1.0.0

* Implementierung der grundlegenden Plugin-Funktionalität
