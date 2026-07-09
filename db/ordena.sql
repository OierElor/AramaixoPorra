-- Karrerak taulari "Ordena" zutabea gehitu (txapelketa barruko karrera-ordena).
-- phpMyAdmin-en exekutatu.
--
-- Ordena = karreraren postua txapelketa barruan (1, 2, 3...). Sailkapenak
-- kalkulatzeko eta bilakaera-grafikoetarako erabiltzen da. NULL bada, Karrerak_ID
-- (sortze-ordena) erabiltzen da ordezko gisa.

ALTER TABLE Karrerak ADD COLUMN Ordena INT DEFAULT NULL;

-- Lehendik dauden karrerei ordena esleitu, txapelketaz, oraingo Karrerak_ID ordenean
-- (sailkapenak berdin mantentzeko). MySQL 8+ (leiho-funtzioak):
UPDATE Karrerak k
JOIN (
    SELECT Karrerak_ID,
           ROW_NUMBER() OVER (PARTITION BY Txapelketa_ID ORDER BY Karrerak_ID) AS rn
    FROM Karrerak
) x ON x.Karrerak_ID = k.Karrerak_ID
SET k.Ordena = x.rn;

-- MySQL 5.7 (leiho-funtziorik gabe) erabiltzen baduzu, aurrekoaren ordez:
-- UPDATE Karrerak k
-- JOIN (
--     SELECT Karrerak_ID,
--            (@rn := IF(@tx = Txapelketa_ID, @rn + 1, 1)) AS rn,
--            (@tx := Txapelketa_ID) AS tx
--     FROM Karrerak CROSS JOIN (SELECT @rn := 0, @tx := 0) v
--     ORDER BY Txapelketa_ID, Karrerak_ID
-- ) x ON x.Karrerak_ID = k.Karrerak_ID
-- SET k.Ordena = x.rn;
