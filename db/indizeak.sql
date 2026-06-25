-- ── Errendimendu indizeak ────────────────────────────────────────────────────
-- phpMyAdmin-en behin exekutatu. Lehendik badaude, errorea jaurtitzen du
-- (ez dago arazorik, indizeak jada badaude).

-- PorraApustuak: ospea-balioa eta porra-ideala tresnek erabiltzen duten
-- (Txapelketa_ID, Txirrindularia_ID) bilaketa azkartzen du
ALTER TABLE PorraApustuak ADD INDEX idx_apustu_txap_txirr (Txapelketa_ID, Txirrindularia_ID);

-- PorraApustuak: ezizen baten apustu guztiak azkar bilatzeko
ALTER TABLE PorraApustuak ADD INDEX idx_apustu_ezizen_txap (Ezizen_ID, Txapelketa_ID);
