-- Karrerei bi ezaugarri berri: DESNIBELA eta "EMAITZARIK EZ" marka.
-- phpMyAdmin-en exekutatu. (db/karrera-motak.sql-en ONDOREN; hura independentea da.)
--
-- 1) Desnibela: metroak (INT). Karreraren desnibel metatua.
--    Admin panelean editatzen da (Karrera motak → Karrerei esleitu).
--    Webgune publikoan EZ da erakusten oraingoz.
--
-- 2) Emaitzarik_Ez: 1 bada, karrera horrek EZ du inoiz emaitzarik izango
--    (adib. bertan behera utzitako etapa, edo puntuaziorako zenbatzen ez dena).
--    Ondorioak:
--      · Urte-orriko akordeoian EZ da agertzen (panel huts iraunkorra saihesteko).
--      · Datu-osasunak EZ du "emaitzarik gabeko etapa" gisa salatzen.
--      · Grafikoetan lehendik ere kanpo zegoen (emaitzarik ez duenez).
--
--    ⚠️ Emaitzak DITUEN karrera bat markatuz gero, emaitzak hor jarraituko dute
--    (ez dira ezabatzen), baina akordeoian ezkutatuko da.

ALTER TABLE Karrerak ADD COLUMN Desnibela INT DEFAULT NULL;
ALTER TABLE Karrerak ADD COLUMN Emaitzarik_Ez TINYINT(1) NOT NULL DEFAULT 0;
