-- Karrerei "EMAITZARIK EZ" marka.
-- phpMyAdmin-en exekutatu.
--
-- Emaitzarik_Ez: 1 bada, karrera horrek EZ du inoiz emaitzarik izango
-- (adib. bertan behera utzitako etapa, edo puntuaziorako zenbatzen ez dena).
-- Admin panelean editatzen da (Txapelketak → Karrerak).
-- Ondorioak:
--   · Urte-orriko akordeoian EZ da agertzen (panel huts iraunkorra saihesteko).
--   · Datu-osasunak EZ du "emaitzarik gabeko etapa" gisa salatzen.
--   · Grafikoetan lehendik ere kanpo zegoen (emaitzarik ez duenez).
--
-- ⚠️ Emaitzak DITUEN karrera bat markatuz gero, emaitzak hor jarraituko dute
-- (ez dira ezabatzen), baina akordeoian ezkutatuko da.

ALTER TABLE Karrerak ADD COLUMN Emaitzarik_Ez TINYINT(1) NOT NULL DEFAULT 0;
