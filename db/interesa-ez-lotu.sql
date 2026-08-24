-- Porralarien sailkapena + ezizenak nahita lotu gabe uztea.
-- phpMyAdmin-en exekutatu.
--
-- 1) Porralariak.Interesa: 1 bada, porralariak webguneko datuetan interesa du (admin marka).
--    ADMIN-EN SOILIK erabiltzen da (sailkapena/iragazkia Porralariak atalean);
--    webgune publikoak EZ du ezer aldatzen honekin.
--
-- 2) PorraEzizenak.Ez_Lotu: 1 bada, ezizen/porra hori NAHITA utzi da porralari batekin
--    lotu gabe (jabeak ez du interesik). Ondorioak:
--      · Datu-osasunak EZ du "lotu gabeko ezizen" gisa salatzen.
--      · Dashboard-eko lotu-gabe kontaketatik kanpo.
--      · "Ezizenak lotu" zeregin-zerrendatik (lotu-gabe iragazkitik) kanpo; talde
--        bereizi batean ikusgai, nahi izanez gero desmarkatzeko.

ALTER TABLE Porralariak   ADD COLUMN Interesa TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE PorraEzizenak ADD COLUMN Ez_Lotu  TINYINT(1) NOT NULL DEFAULT 0;
