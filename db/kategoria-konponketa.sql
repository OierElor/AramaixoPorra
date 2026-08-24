-- Kategoria baliogabeak konpondu (behin bakarrik exekutatu, phpMyAdmin-en).
-- «3» eta «Pro» balio orphanak kentzeko (KAT_AUKERAK-en ez daudenak).
--
--   389  Grand Prix Cycliste de Québec   «3»  → «4»
--   390  Grand Prix Cycliste de Montréal «Pro» → «4»
--   391  World championship              «Pro» → «Berezia»
--
-- Ondoren, erabiltzen diren kategoriak: Etapa, 4, 5, Monumentua, Proseries, Berezia
-- (denak admin dropdown-eko KAT_AUKERAK-en).

UPDATE Karrerak SET Kategoria = '4'       WHERE Karrerak_ID = 389;
UPDATE Karrerak SET Kategoria = '4'       WHERE Karrerak_ID = 390;
UPDATE Karrerak SET Kategoria = 'Berezia' WHERE Karrerak_ID = 391;
