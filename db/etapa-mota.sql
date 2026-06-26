-- Karrerak taulari Mota zutabea gehitu
-- phpMyAdmin-en exekutatu (aramaixoporra.eus → Plesk → Bases de datos)

ALTER TABLE Karrerak ADD COLUMN Mota VARCHAR(50) DEFAULT NULL;

-- ── Itzuli handien etapak (Kategoria='Etapa') ─────────────────────────────────

-- Lauzepa: etapa launa, sprint masiboarekin
UPDATE Karrerak SET Mota='Lauzepa' WHERE Kategoria='Etapa' AND (
  Izena LIKE '%lau%' OR Izena LIKE '%sprint%' OR Izena LIKE '%plana%'
);

-- Denbora-proba: kronometrikoa
UPDATE Karrerak SET Mota='Denbora-proba' WHERE Kategoria='Etapa' AND (
  Izena LIKE '%kronometri%' OR Izena LIKE '%contrarreloj%' OR Izena LIKE '%ITT%'
  OR Izena LIKE '%denbora proba%' OR Izena LIKE '%denbora-proba%'
  OR Izena LIKE '% CRI%' OR Izena LIKE '%time trial%'
);

-- Talde denbora-proba
UPDATE Karrerak SET Mota='Talde denbora-proba' WHERE Kategoria='Etapa' AND (
  Izena LIKE '%talde%' OR Izena LIKE '%TTT%' OR Izena LIKE '%equipo%'
  OR Izena LIKE '%team time%'
);

-- ── Klasikak ──────────────────────────────────────────────────────────────────

-- Harri-zorua: Paris-Roubaix eta antzekoak
UPDATE Karrerak SET Mota='Harri-zorua' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Roubaix%'
);

-- Flandriar: Tour of Flanders, E3, A través de Flandes, Gent-Wevelgem, ...
UPDATE Karrerak SET Mota='Flandriar' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Flandres%' OR Izena LIKE '%Flanders%' OR Izena LIKE '%Flandria%'
  OR Izena LIKE '%E3%' OR Izena LIKE '%Flandes%'
  OR Izena LIKE '%Gent%Wevelgem%' OR Izena LIKE '%Gent - Wevelgem%'
  OR Izena LIKE '%Kuurne%' OR Izena LIKE '%Dwars%'
);

-- Ardenak: Amstel, La Flèche, Liège-Bastogne-Liège
UPDATE Karrerak SET Mota='Ardenak' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Amstel%' OR Izena LIKE '%Fl%che%' OR Izena LIKE '%Liege%'
  OR Izena LIKE '%Li%ge%' OR Izena LIKE '%LBL%' OR Izena LIKE '%Wallonne%'
);

-- Udaberri irekiera: Strade Bianche, Omloop, Milano-Torino, ...
UPDATE Karrerak SET Mota='Udaberri irekiera' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Strade%' OR Izena LIKE '%Omloop%' OR Izena LIKE '%Milano%Torino%'
  OR Izena LIKE '%Faun%Ardèche%' OR Izena LIKE '%Ardech%'
);

-- Esprinta: Milan-San Remo, Scheldeprijs, Eureka Mobia, ...
UPDATE Karrerak SET Mota='Esprinta' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Milan%San Remo%' OR Izena LIKE '%Milano%Sanremo%'
  OR Izena LIKE '%Scheldeprijs%' OR Izena LIKE '%Milano - Sanremo%'
  OR Izena LIKE '%San Juan%' OR Izena LIKE '%Eureka%'
);

-- Udako klasika: Donostia, San Sebastian, GP Quebec, GP Montreal, ...
UPDATE Karrerak SET Mota='Udako klasika' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Donostia%' OR Izena LIKE '%San Sebasti%'
  OR Izena LIKE '%Quebec%' OR Izena LIKE '%Montreal%'
  OR Izena LIKE '%Bretagne%' OR Izena LIKE '%Hambourg%' OR Izena LIKE '%Hamburg%'
  OR Izena LIKE '%Varese%' OR Izena LIKE '%Coppa Agostoni%'
);

-- Mendi-klasika: Il Lombardia, Dolomiti, ...
UPDATE Karrerak SET Mota='Mendi-klasika' WHERE Kategoria<>'Etapa' AND (
  Izena LIKE '%Lombardi%' OR Izena LIKE '%Dolomiti%' OR Izena LIKE '%Tre Valle%'
  OR Izena LIKE '%Giro dell%Emilia%' OR Izena LIKE '%Coppa Sabatini%'
);

-- Egiaztatu:
-- SELECT Izena, Kategoria, Mota FROM Karrerak ORDER BY Kategoria, Mota, Izena;
