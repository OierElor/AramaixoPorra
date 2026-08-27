-- "Lasterketak" taula: klasika kanonikoak (urte arteko identitatea) + webgune ofizialak.
-- phpMyAdmin-en exekutatu.
--
-- ARAZOA: klasikak urtez urte errepikatzen dira, baina `Karrerak`-eko errenkada bakoitza
-- independentea da eta izenak ez dira egonkorrak urte artean:
--   'Ronde van Vlaanderen - Tour des Flandres' (2024/2026) vs 'Tour des Flandres' (2025)
--   'Classic Brugge-De Panne' (2024/2025)      vs 'Ronde Van Brugge - Tour of Bruges' (2026)
--   'BEMER Cyclassics' (2024)                  vs 'ADAC Cyclassics' (2025)
--
-- KONPONBIDEA: lasterketa kanoniko bat (`Lasterketak`) eta urteko karrerek hara apuntatzen
-- dute (`Karrerak.Lasterketa_ID`). `PorraEzizenak` → `Porralariak` ereduaren parekoa.
-- Webgune ofiziala KANONIKOAN dago: behin sartuta, urte guztietan balio du (urte berri bat
-- lotzean automatikoki heredatzen da).
--
-- `Txapelketak.Web_Ofiziala` itzuli handientzat da (txapelketa mailakoa; etapek EZ dute).
--
-- Admin panela → Lasterketak (lotu / bateratu / URLa) eta Baliabideak (URLa ere bai).

CREATE TABLE Lasterketak (
  Lasterketa_ID INT AUTO_INCREMENT PRIMARY KEY,
  Izena         VARCHAR(255) NOT NULL,
  Web_Ofiziala  VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE Karrerak    ADD COLUMN Lasterketa_ID INT DEFAULT NULL;
ALTER TABLE Txapelketak ADD COLUMN Web_Ofiziala VARCHAR(255) DEFAULT NULL;

-- ── Hazia: klasikak IZEN ZEHATZAREN arabera taldekatu ────────────────────────
-- Berdin-berdin idatzitakoak (gehiengoa) automatikoki lotzen dira. Izen-aldaerak
-- (goiko 3-4 kasuak) adminetik bateratzen dira: Lasterketak → «Bateratu».

INSERT INTO Lasterketak (Izena)
SELECT DISTINCT k.Izena
FROM Karrerak k
JOIN Txapelketak t ON t.Txapelketa_ID = k.Txapelketa_ID
WHERE t.Izena LIKE 'Klasikak%';

UPDATE Karrerak k
JOIN Txapelketak t ON t.Txapelketa_ID = k.Txapelketa_ID
JOIN Lasterketak l ON l.Izena = k.Izena
SET k.Lasterketa_ID = l.Lasterketa_ID
WHERE t.Izena LIKE 'Klasikak%';
