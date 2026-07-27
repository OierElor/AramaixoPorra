-- Txapelketa AMAITUTA marka.
-- phpMyAdmin-en exekutatu.
--
-- Amaituta: 1 bada, txapelketa itxita/bukatuta dago.
--   "Puntu finalak" tresnak (admin → Sartu → Puntu finalak) jartzen du 1,
--   sailkapen orokorreko eta mendiko puntu finalak sartu ondoren.
--   Aldi berean Porra_Irekita = 0 jartzen da (aurre-porrak itxi).
--
--   Oraingoz admin-en bakarrik erabiltzen da (adierazlea + iragazkia);
--   webgune publikoan EZ da erakusten (nahi izanez gero geroago).

ALTER TABLE Txapelketak ADD COLUMN Amaituta TINYINT(1) NOT NULL DEFAULT 0;
