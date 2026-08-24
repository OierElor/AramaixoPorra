-- Txapelketa-mailako fitxategiak DBra: arauak / startlist / porrak PDFak.
-- phpMyAdmin-en exekutatu.
--
-- Orain arte `js/txapelketak.js` konfigurazio estatikoan zeuden. Hemendik aurrera
-- admin paneletik (Baliabideak atala) igo eta esleitzen dira, fitxategirik editatu gabe.
-- Balioa = PDF fitxategi-izena (data/<karpeta>/-n dagoena). NULL bada, `txapelketak.js`-eko
-- balioa erabiltzen da (atzerako bateragarritasuna).

ALTER TABLE Txapelketak
  ADD COLUMN Arauak_PDF    VARCHAR(255) NULL,
  ADD COLUMN Dortsalak_PDF VARCHAR(255) NULL,
  ADD COLUMN Porrak_PDF    VARCHAR(255) NULL;
