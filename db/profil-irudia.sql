-- Karrerak taulari "Profil_Irudia" zutabea gehitu (etapa-profilaren lotura esplizitua).
-- phpMyAdmin-en exekutatu.
--
-- Profil_Irudia = profil-irudiaren bidea, "profilak" karpetatik zintzilik
-- (adib. 'tour26/Etapa9.jpg' edo 'tour26/Malemort - Ussel.jpg').
-- "profilak" oinarria admin-etik alda daiteke (api/ezarpenak.php), beraz bidea
-- erlatiboa da folder-aldaketa jasateko.
--
-- Hutsik (NULL) bada, izen-konbentziora jotzen da ordezko gisa: 'Etapa{Ordena}.jpg'
-- (itzuliak) edo js/txapelketak.js-eko `irudiak` mapa (klasikoak).
--
-- Admin panela → Fitxategiak → profil-irudi bati karrera lotu.

ALTER TABLE Karrerak ADD COLUMN Profil_Irudia VARCHAR(255) DEFAULT NULL;
