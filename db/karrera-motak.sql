-- Karrera-motak: katalogo kudeagarria + karrerekiko lotura.
-- phpMyAdmin-en exekutatu.
--
-- MOTA = karreraren ezaugarri bat (desnibela/lurraldea), adib. '<2000 m', '2000-3000 m'.
--
-- ⚠️ EZ NAHASI `Karrerak.Kategoria`-rekin: hura 'Etapa' (itzuliak) edo UCI kodea (klasikoak)
-- da, eta akordeoiaren iragazkia eta puntuazioa gidatzen ditu. MOTA independentea da.
--
-- Motak admin paneletik kudeatzen dira (Karrera motak atala): sortu, berrizendatu, ezabatu,
-- eta karrera bakoitzari esleitu. Izena taula BAKARREAN dagoenez, mota bat berrizendatzeak
-- karrera guztietan eguneratzen du automatikoki.

CREATE TABLE KarreraMotak (
  Mota_ID INT NOT NULL AUTO_INCREMENT,
  Izena   VARCHAR(80) NOT NULL,
  Ordena  INT DEFAULT NULL,          -- zerrendan agertzeko ordena
  PRIMARY KEY (Mota_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Karrera bakoitzaren mota (NULL = motarik gabe).
-- FK murrizketarik EZ (proiektuaren ereduari jarraituz); osotasuna kodean bermatzen da:
-- mota bat ezabatzean, hura darabilten karrerak Mota_ID = NULL geratzen dira.
ALTER TABLE Karrerak ADD COLUMN Mota_ID INT DEFAULT NULL;

-- Hasierako motak (admin-etik alda/gehi/ezaba daitezke)
INSERT INTO KarreraMotak (Izena, Ordena) VALUES
  ('<2000 m',     1),
  ('2000-3000 m', 2),
  ('3000-4000 m', 3),
  ('>4000 m',     4);
