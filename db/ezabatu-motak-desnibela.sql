-- GARBIKETA: karrera-motak eta desnibela ezaugarriak kentzea.
-- phpMyAdmin-en ESKUZ exekutatu (ez da migrazio arrunta: ez da panelean agertzen).
--
-- ⚠️ DATUAK BETIKO GALTZEN DIRA. Egin babeskopia bat aurretik
--    (admin panela → Datu-basea → ⬇ Babeskopia).
--
-- Ezaugarri hauen kodea kenduta dago jada; script honek eskema garbitzen du:
--   · Karrerak.Mota_ID  — lehen `KarreraMotak` katalogora lotzen zuen
--   · Karrerak.Desnibela — metroak
--   · KarreraMotak      — moten katalogoa
--
-- ⚠️ EZ ukitu `Karrerak.Emaitzarik_Ez`: ezaugarri BIZIA da (db/emaitzarik-ez.sql).
--    Urte-orriko akordeoiak eta datu-osasunak erabiltzen dute.

ALTER TABLE Karrerak DROP COLUMN Mota_ID;
ALTER TABLE Karrerak DROP COLUMN Desnibela;
DROP TABLE IF EXISTS KarreraMotak;
