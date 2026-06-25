-- Aramaixo Porra — MySQL eskema (SQLite-tik migratua)
-- Inportatu phpMyAdmin-en edo: mysql -u USER -p DBIZENA < schema.sql
-- Gero: mysql -u USER -p DBIZENA < data.sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS Sariak, TxirrindulariakTxapleketanParteHartzea,
    TxapelketaSailkapenaPorralariak, TxapelketaSailkapenaTxirrindulariak,
    TxapelketaEmaitzaPorralariak, TxapelketaEmaitzaTxirrindulariak,
    KarreraSailkapena, PorraApustuak, PorralariTaldeenEzizenak,
    PorraEzizenak, Karrerak, Txirrindulariak, Porralariak, Txapelketak;

CREATE TABLE Txapelketak (
    Txapelketa_ID INT NOT NULL AUTO_INCREMENT,
    Izena VARCHAR(255) NOT NULL,
    Urtea INT NOT NULL,
    PRIMARY KEY (Txapelketa_ID),
    UNIQUE KEY uq_txapelketa (Izena, Urtea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Porralariak (
    Porralaria_ID INT NOT NULL AUTO_INCREMENT,
    Izena VARCHAR(255) NOT NULL,
    Zenbat_Porra INT DEFAULT 1,
    PRIMARY KEY (Porralaria_ID),
    UNIQUE KEY uq_porralari (Izena)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Txirrindulariak (
    Txirrindularia_ID INT NOT NULL AUTO_INCREMENT,
    Izena VARCHAR(255) NOT NULL,
    PRIMARY KEY (Txirrindularia_ID),
    UNIQUE KEY uq_txirrindulari (Izena)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Karrerak (
    Karrerak_ID INT NOT NULL AUTO_INCREMENT,
    Txapelketa_ID INT NOT NULL,
    Izena VARCHAR(255) NOT NULL,
    Urtea INT NOT NULL,
    Kategoria VARCHAR(50),
    PRIMARY KEY (Karrerak_ID),
    UNIQUE KEY uq_karrera (Izena, Urtea),
    KEY idx_karrera_txap (Txapelketa_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE PorraEzizenak (
    Ezizen_ID INT NOT NULL AUTO_INCREMENT,
    Txapelketa_ID INT NOT NULL,
    Ezizena VARCHAR(255) NOT NULL,
    PRIMARY KEY (Ezizen_ID),
    UNIQUE KEY uq_ezizena (Txapelketa_ID, Ezizena)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE PorralariTaldeenEzizenak (
    Ezizen_ID INT NOT NULL,
    Porralaria_ID INT NOT NULL,
    PRIMARY KEY (Ezizen_ID, Porralaria_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE PorraApustuak (
    Txapelketa_ID INT NOT NULL,
    Ezizen_ID INT NOT NULL,
    Txirrindularia_ID INT NOT NULL,
    PRIMARY KEY (Txapelketa_ID, Ezizen_ID, Txirrindularia_ID),
    KEY idx_apustu_txirr (Txirrindularia_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE KarreraSailkapena (
    Karrera_ID INT NOT NULL,
    Txirrindularia_ID INT NOT NULL,
    Puntuak INT NOT NULL,
    Sailkapena INT NOT NULL,
    PRIMARY KEY (Karrera_ID, Txirrindularia_ID),
    KEY idx_ks_txirr (Txirrindularia_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TxapelketaEmaitzaTxirrindulariak (
    Txapelketa_ID INT NOT NULL,
    Txirrindularia_ID INT NOT NULL,
    Posizioa INT NOT NULL,
    Puntuak INT NOT NULL,
    Puntuak_Sailkapen_Nag INT,
    Puntuak_Mendian INT,
    Zenbatek INT,
    PRIMARY KEY (Txapelketa_ID, Txirrindularia_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TxapelketaEmaitzaPorralariak (
    Txapelketa_ID INT NOT NULL,
    Ezizen_ID INT NOT NULL,
    Posizioa INT NOT NULL,
    Puntuak INT NOT NULL,
    Puntuak_Mendikoa INT,
    Puntuak_Generala INT,
    PRIMARY KEY (Txapelketa_ID, Ezizen_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TxapelketaSailkapenaTxirrindulariak (
    Txapelketa_ID INT NOT NULL,
    Txirrindularia_ID INT NOT NULL,
    Azken_Karrera_ID INT NOT NULL,
    Puntuak_Totalean INT NOT NULL,
    Puntuak_Azken_Karrera INT NOT NULL,
    Puntuak_Sailkapen_nagusia INT,
    Puntuak_Mendian INT,
    Eboluzioa INT NOT NULL DEFAULT 0,
    PRIMARY KEY (Txapelketa_ID, Txirrindularia_ID, Azken_Karrera_ID),
    KEY idx_sst (Txapelketa_ID, Azken_Karrera_ID, Txirrindularia_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TxapelketaSailkapenaPorralariak (
    Txapelketa_ID INT NOT NULL,
    Ezizen_ID INT NOT NULL,
    Azken_Karrera_ID INT NOT NULL,
    Puntuak_Totalean INT NOT NULL,
    Puntuak_Azken_Karrera INT NOT NULL,
    Puntuazio_Finala INT NOT NULL DEFAULT 0,
    Puntuazioa_Fin_Mendikoa INT,
    Puntuazioa_Fin_Generala INT,
    PRIMARY KEY (Txapelketa_ID, Ezizen_ID, Azken_Karrera_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TxirrindulariakTxapleketanParteHartzea (
    TxapelketaID INT NOT NULL,
    TxirrindulariaID INT NOT NULL,
    Dortsala INT NOT NULL,
    PRIMARY KEY (TxapelketaID, TxirrindulariaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Sariak (
    Txapelketa_ID INT NOT NULL,
    Posizioa INT NOT NULL,
    Saria VARCHAR(255) NOT NULL,
    PRIMARY KEY (Txapelketa_ID, Posizioa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
