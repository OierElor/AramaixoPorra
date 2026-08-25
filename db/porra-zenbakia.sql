-- Porra-zenbakia: PorraEzizenak taulari zenbaki bat, porra-zerrendako ordena adierazteko.
-- phpMyAdmin-en exekutatu.
--
-- Zenbakia = porraren zenbakia (erregistro-zerrendakoa). Oro har Ezizen_ID ordena da, baina
-- editagarria da (admin → Ezizenak lotu) salbuespenetarako. Bero-mapak hortik ordenatzen ditu
-- zutabeak.
--
-- Hasieratu: txapelketaz txapelketa 1..N, Ezizen_ID ordenan (uneko ordena). MySQL 8 (ROW_NUMBER).

ALTER TABLE PorraEzizenak ADD COLUMN Zenbakia INT NULL;

UPDATE PorraEzizenak pe
JOIN (
  SELECT Ezizen_ID, ROW_NUMBER() OVER (PARTITION BY Txapelketa_ID ORDER BY Ezizen_ID) AS rn
  FROM PorraEzizenak
) t ON t.Ezizen_ID = pe.Ezizen_ID
SET pe.Zenbakia = t.rn;
