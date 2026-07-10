-- Txapelketak taulari aurre-porren konfigurazioa gehitu.
-- phpMyAdmin-en exekutatu.
--
-- Porra_Irekita  = 1 bada, porralariek beren porra AURRETIK bidal dezakete
--                  /tresnak/porra-bidali/ orritik. 0 bada, api/porra.php-k 403 itzultzen du.
-- Apustu_Kopurua = porra batek zenbat txirrindulari izan behar dituen (itzuliak 15,
--                  klasikak 25). NULL bada, kodeak 15 hartzen du.
--
-- OHARRA: bidalitako porrak EZ dira datu-basean gordetzen. Zutabe hauek adminaren
-- konfigurazioa besterik ez dira; apustuak admin/aurre-porrak.log-era eta emailera doaz,
-- eta adminak berrikusi ondoren sartzen dira PorraApustuak taulan.

ALTER TABLE Txapelketak ADD COLUMN Porra_Irekita TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE Txapelketak ADD COLUMN Apustu_Kopurua INT DEFAULT NULL;

-- Lehendik dauden txapelketei apustu-kopurua esleitu (klasikak 25, gainerakoak 15).
UPDATE Txapelketak
SET Apustu_Kopurua = CASE WHEN Izena LIKE 'Klasikak%' THEN 25 ELSE 15 END;

-- Txapelketa bat irekitzeko (adibidez Tour de France 2026, ID 17):
--   UPDATE Txapelketak SET Porra_Irekita = 1 WHERE Txapelketa_ID = 17;
-- Admin paneleko "Txapelketak" ataleko kontrolek gauza bera egiten dute.
