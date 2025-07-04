<?php
session_start();
if (!isset($_SESSION['id'])) {
    // Verifica che l'utente non sia già sulla pagina di login
    if (basename($_SERVER['PHP_SELF']) != 'login.php') {
        header("Location: login.php");
        exit;
    }
} else {
    // L'utente è autenticato, ora controlliamo il dispositivo
    $userAgent = $_SERVER['HTTP_USER_AGENT'];

    if (preg_match('/iphone|ipod|android|blackberry|opera mini|windows phone/i', $userAgent)) {
        // Se il dispositivo è mobile
        if (basename($_SERVER['PHP_SELF']) != '1x2.php') {
            header('Location: 1x2.php');
            exit;
        }
    } else {
        // Se il dispositivo è un PC
        if (basename($_SERVER['PHP_SELF']) != '1x2.php') {
            header('Location: 1x2.php');
            exit;
        }
    }
}
$mysqli = new mysqli("localhost", "root", "sticazzi", "calcio");

// Controlla connessione
if ($mysqli->connect_error) {
    die("Connessione fallita: " . $mysqli->connect_error);
}

// ID dell'utente da cercare (può provenire da una sessione o input)
$utente_id = $_SESSION['id']; // Cambia questo con l'ID dell'utente attuale
$is_admin = $_SESSION['is_admin'];
// Query per ottenere i dati dell'utente
$utente_stmt = $mysqli->prepare("SELECT nome, cognome, email, abbonamento_tipo, abbonamento_scadenza, abbonamento_attivo, is_admin FROM utenti WHERE id = ?");
$utente_stmt->bind_param("i", $utente_id);
$utente_stmt->execute();
$utente_result = $utente_stmt->get_result();

// Stampa tutto l'array utente per debug
if ($utente_result->num_rows > 0) {
    $utente = $utente_result->fetch_assoc();
// var_dump($utente);  // Verifica l'intero array qui
} else {
    echo "Utente non trovato.";
}

$utente_stmt->close();
$mysqli->close();	
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche Calcio Mercati 1x2 e DC</title>
   
 <style>
 /* Stili generali */
h1 {
    font-size: 20px;
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

h2 {
    text-align: center;
}

.controls-wrapper {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
    position: sticky;
    top: 0;
    background-color: white;
    z-index: 1000;
    padding: 5px 0;
}

.control-container {
    display: flex;
    flex-direction: column;
    max-width: 100%;
    font-size: 12px;
    text-align: center;
}

.sticky-soglia {
    position: sticky;
    top: 0;
    background-color: white;
    z-index: 1000;
    padding: 5px 0;
}

#soglia-select option:first-child {
    color: #888;
    font-style: italic;
    font-weight: bold;
}

#soglia-select {
    color: #000;
}

/* Stili per pulsanti e select */
button {
    background-color: #999999;
    color: white;
    padding: 5px 10px;
    font-size: 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin: 0 auto;
    display: block;
}

button:hover {
    background-color: #00795e;
}

select {
    padding: 8px;
    font-size: 12px;
    display: block;
    margin: 0 auto;
}
/* Stile per la tabella con header fisso */
.table-container {
    max-height: 80vh; /* Altezza massima prima dello scroll */
    overflow-y: auto; /* Abilita lo scroll verticale */
    margin: 20px auto;
    position: relative;
}

th {
    position: sticky;
    top: 0; /* Fissa l'header in alto */
    z-index: 10; /* Assicura che l'header sia sopra al contenuto */
    background-color: #999999;
    color: white;
    text-transform: uppercase;
    font-size: 14px;
    border: 1px solid #ccc;
    padding: 12px;
    text-align: center;
}
/* Stile della tabella */
table {
    width: 100%;
    border-collapse: collapse;
    background-color: white;
    margin-bottom: 10px;
}

th, td {
    border: 1px solid #ccc;
    padding: 12px;
    text-align: center;
}

th {
    background-color: #999999;
    color: white;
    text-transform: uppercase;
    font-size: 14px;
    position: sticky;
    top: 0;
    z-index: 10;
}

th.sortable {
    cursor: pointer;
}

td {
    font-size: 14px;
    color: #333;
}

tr:nth-child(even) {
    background-color: #f2f2f2;
}

/* Loghi delle squadre */
.team-logo img {
    max-height: 40px;
    width: auto;
    display: block;
    margin: 0 auto;
}

/* Stili per dispositivi mobili */
@media (max-width: 600px) {
    .team-logo img {
        height: 20px;
    }
    
    .controls-wrapper {
        flex-direction: column;
        gap: 10px;
    }
}

/* Icone di ordinamento */
.sort-icons {
    display: inline-block;
    margin-left: 5px;
}

.up-arrow {
    display: inline !important;
}

.down-arrow {
    display: none !important;
}

.sort-icons .up-arrow,
.sort-icons .down-arrow {
    font-size: 12px;
    cursor: pointer;
}
</style>

 <script>
    function changePage() {
    const page = document.getElementById('page-select').value;

    if (page) {
        // Fai una richiesta AJAX per rimuovere la variabile dalla sessione
        fetch('remove_soglia.php')
            .then(response => {
                if (response.ok) {
                    // Reindirizza alla pagina selezionata
                    window.location.href = page;
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

        function changeGiornata(offset) {
            var select = document.getElementById('giornata-select');
            var currentGiornata = parseInt(select.value);
            var newGiornata = currentGiornata + offset;
            if (newGiornata >= 1 && newGiornata <= 38) {
                select.value = newGiornata;
                window.location.href = '?giornata=' + newGiornata;
            }
        }

        function updateGiornata() {
            var select = document.getElementById('giornata-select');
            var selectedGiornata = select.value;
            window.location.href = '?giornata=' + selectedGiornata;
        }

function changeSoglia() {
            const selectElement = document.getElementById("soglia-select");
            const soglia = selectElement.value;
            console.log("Soglia selezionata: ", soglia);

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'action': 'update_soglia',
                    'soglia': soglia
                })
            }).then(response => response.text())
              .then(data => {
                console.log("Risposta dal server:", data);
                // Ricarica la pagina dopo aver aggiornato la soglia
                window.location.reload();
              })
              .catch(error => console.error('Errore:', error));
        }

        // Funzione per ordinare la tabella
        function sortTable(columnIndex) {
            const table = document.querySelector('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const header = table.querySelectorAll('th')[columnIndex];
            const isAscending = header.getAttribute('data-sort') === 'asc';
            
            // Cambia l'icona e lo stato di ordinamento
            header.setAttribute('data-sort', isAscending ? 'desc' : 'asc');
            
            // Resetta le icone nelle altre colonne
            document.querySelectorAll('th.sortable').forEach(th => {
                if (th !== header) {
                    th.removeAttribute('data-sort');
                }
            });
            
            // Ordina le righe
            rows.sort((a, b) => {
                const aValue = parseFloat(a.cells[columnIndex].textContent);
                const bValue = parseFloat(b.cells[columnIndex].textContent);
                
                return isAscending ? aValue - bValue : bValue - aValue;
            });
            
            // Rimuovi le righe esistenti
            rows.forEach(row => tbody.removeChild(row));
            
            // Aggiungi le righe ordinate
            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
   
</head>
<body>
<div style="border: 1px solid #ccc; padding: 5px 8px; margin: 10px auto; border-radius: 12px; max-width: 100%; width: 85%; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #f8f9fa, #e9ecef); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">
    <a href="index.php">
        <img src="./loghi/stats.jpg" alt="Logo" style="height: 30px; margin-right: 10px;"> 
    </a>
    <div style="display: flex; flex-wrap: wrap; gap: 8px; line-height: 1.2;">
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Nome:</strong> <?php echo $utente['nome']; ?></p>
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Cognome:</strong> <?php echo $utente['cognome']; ?></p>
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Email:</strong> <?php echo $utente['email']; ?></p>
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Tipo Abbonamento:</strong> <?php echo $utente['abbonamento_tipo'] ? $utente['abbonamento_tipo'] : 'N/A'; ?></p>
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Scadenza:</strong> <?php echo $utente['abbonamento_scadenza'] ? $utente['abbonamento_scadenza'] : 'N/A'; ?></p>
        <p style="margin: 0; font-size: 14px; color: #000;"><strong>Abbonamento Attivo:</strong> <?php echo $utente['abbonamento_attivo'] ? 'Sì' : 'No'; ?></p>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <?php if ($utente['is_admin']) { ?> 
            <a href="admin.php" style="padding: 4px 12px; font-size: 14px; background-color: #0088cc; color: white; border-radius: 5px; text-decoration: none;">Admin</a>
        <?php } else { ?>
            <a href="rinnova.php" style="padding: 4px 12px; font-size: 14px; background-color: #00795e; color: white; border-radius: 5px; text-decoration: none;">Rinnova</a>
        <?php } ?>
        <a href="index.php" style="padding: 4px 12px; font-size: 14px; background-color: #0088cc; color: white; border-radius: 5px; text-decoration: none;">Home</a>
        <a href="https://t.me/AssistenzaTopItalia" target="_blank" style="display: flex; align-items: center; text-decoration: none; color: #00795e;">
            <span style="font-size: 14px; color: #000;"><strong>Assistenza</strong></span>
            <img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" alt="Telegram" style="height: 25px; margin-left: 5px;">
        </a>
    </div>
</div>

<!-- Selettori affiancati per data e mercato -->
<div class="controls-wrapper" style="margin-top: 2px; margin-bottom: 1px; display: flex; justify-content: center; gap: 20px;">

    <!-- Selettore per il mercato -->
    <div class="control-container" style="flex: 1; max-width: 300px;">
        <select id='page-select' onchange='changePage()' style="width: 100%;">
            <option value="" disabled selected>1X2 ⚽</option>
		    <option value='DC.php'>Doppia Chance ⚽ </option>
            <option value='gol.php'>Goal/NoGoal ⚽ </option>
            <option value='multigol.php'>Multigol ⚽ </option>
       
            <option value='tiriportaa.php'>Tiri in porta 🥅🏠✈️</option>
            <option value='tiritotalia.php'>Tiri Totali 🎯🏠✈️ </option>
            <option value='cornera.php'>Angoli 🚩🏠✈️ </option>
        </select>
    </div>

    <!-- Selettore per la data -->
    <div class="control-container" style="flex: 1; max-width: 300px;">
        <form method="get">
            <select name="data" id="date-select" onchange="this.form.submit()" style="width: 100%;">
                <option value="">Seleziona Data (Oggi)</option>
                <option value="tutte">Tutte (da oggi a 5 giorni)</option>
                <?php
                $today = new DateTime(); // Data odierna
                for ($i = 1; $i <= 4; $i++) {
                    $date = clone $today; // Clona la data odierna per evitare modifiche in-place
                    $date->modify("+$i day");
                    $formattedDate = $date->format('Y-m-d'); // Formatta la data
                    $displayDate = $date->format('d/m/Y'); // Data da mostrare
                    $selected = (isset($_GET['data']) && $_GET['data'] === $formattedDate) ? 'selected' : '';
                    echo "<option value='$formattedDate' $selected>$displayDate</option>";
                }
                ?>
            </select>
        </form>
    </div>
    

</div>

<div style="clear: both;"></div> <!-- Questo forza la tabella a iniziare sotto il selettore -->

<?php
// Connessione al database
$host = 'localhost';
$db = 'calcio'; 
$user = 'root'; 
$pass = 'sticazzi';   
$conn = new mysqli($host, $user, $pass, $db);
// Verifica la connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Ottieni la data selezionata o usa oggi come default
$selectedDate = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$showAll = ($selectedDate === 'tutte');

//TEST MIO
// Funzione per normalizzare i nomi delle squadre (rimuove dieresi/accenti)
function normalizeTeamName($teamName) {
    $replacements = [
        'Ö' => 'O', 'ö' => 'o',
        'Ü' => 'U', 'ü' => 'u',
        'Ä' => 'A', 'ä' => 'a',
        'Å' => 'A', 'å' => 'a',
        'É' => 'E', 'é' => 'e',
        'È' => 'E', 'è' => 'e',
        'Ê' => 'E', 'ê' => 'e',
        'Ë' => 'E', 'ë' => 'e',
        'Á' => 'A', 'á' => 'a',
        'À' => 'A', 'à' => 'a',
        'Â' => 'A', 'â' => 'a',
        'Ã' => 'A', 'ã' => 'a',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ç' => 'C', 'ç' => 'c',
        'ß' => 'ss',
        'Ø' => 'O', 'ø' => 'o',
        'Æ' => 'AE', 'æ' => 'ae',
        'Ð' => 'D', 'ð' => 'd',
        'Þ' => 'TH', 'þ' => 'th',
        '/' => '',
    ];
    
    return strtr($teamName, $replacements);
}

// Funzione per calcolare il punteggio di forma da una stringa come "V,N,P,V"
function calcolaPunteggioForma($forma) {
    $punteggio = 0;
    $partite = explode(',', $forma); // Splitta la stringa in un array di risultati
    foreach ($partite as $partita) {
        if ($partita == 'V') {
            $punteggio += 1; // Vittoria
        } elseif ($partita == 'N') {
            $punteggio += 0; // Pareggio
        } elseif ($partita == 'P') {
            $punteggio -= 1; // Sconfitta
        }
    }
    return $punteggio;
}

// Costruisci la query SQL in base alla data selezionata
$sql = "
SELECT 
    p.squadra_casa, 
    p.squadra_ospite, 
    p.partita, 
    p.data_partita,
    p.campionato,
    p.link,
    classifiche_casa.overall_rank AS classifica_casa,
    classifiche_ospite.overall_rank AS classifica_ospite,
    classifiche_casa.overall_points AS punti_casa,
    classifiche_ospite.overall_points AS punti_ospite,
    classifiche_casa.overall_matches_played AS GCAS_casa,
    classifiche_casa.overall_wins AS VCAS_casa,
    classifiche_casa.overall_draws AS XCAS_casa,
    classifiche_casa.overall_losses AS SCAS_casa,
    classifiche_ospite.overall_matches_played AS GFCS_ospite,
    classifiche_ospite.overall_wins AS VFCS_ospite,
    classifiche_ospite.overall_draws AS XFCS_ospite,
    classifiche_ospite.overall_losses AS SFCS_ospite,
    
    q.odd1 AS odd1,
    q.oddx AS oddx,
    q.odd2 AS odd2,
    q.odd1X AS odd1X,
    q.oddX2 AS oddX2,
    q.odd12 AS odd12,
    classifiche_casa.overall_recent_form AS recent_form_casa,
    classifiche_ospite.overall_recent_form AS recent_form_ospite
FROM 
    partiteseriea p
JOIN 
    classifiche classifiche_casa ON p.squadra_casa = classifiche_casa.squadra COLLATE utf8mb4_general_ci
JOIN 
    classifiche classifiche_ospite ON p.squadra_ospite = classifiche_ospite.squadra COLLATE utf8mb4_general_ci
JOIN 
    quoteidealbet q ON p.partita = q.partita COLLATE utf8mb4_general_ci
WHERE 
    p.data_partita >= CURDATE()";

// Aggiungi condizione per la data se non è selezionato "tutte"
if (!$showAll) {
    $sql .= " AND DATE(p.data_partita) = '$selectedDate'";
}

$sql .= " ORDER BY p.data_partita ASC;";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
	
    echo " <div class='table-container'> 
	   <table border='1'>
            <thead>
            <tr>
            <th style='background-color: #9ca6ab;'>Data Partita</th>
            <th style='background-color: #9ca6ab;'>Lega</th>
            <th style='background-color: #9ca6ab;'>Link</th>
            <th style='background-color: #5196b8;'>Logo</th>
            <th style='background-color: #5196b8;'>Casa</th>
            <th style='background-color: #5196b8;'>Forma</th>
            <th style='background-color: #e367a3;'>Logo</th>
            <th style='background-color: #e367a3;'>Ospite</th>
            <th style='background-color: #e367a3;'>Forma</th>
			            <th style='background-color:#70b851;'>%1 BOOK</th>
            <th style='background-color:#70b851;'>%X BOOK</th>
            <th style='background-color:#70b851;'>%2 BOOK</th>
            <th style='background-color:#70b851;'>%1 ALGO</th>
            <th style='background-color:#70b851;'>%X ALGO </th>
            <th style='background-color:#70b851;'>%2 ALGO</th>

            <th class='sortable' style='background-color:#f5a742;' onclick='sortTable(15)'>%1 ATT.<span class='sort-icons'><span class='up-arrow'>↑</span><span class='down-arrow'>↓</span></span></th>
                    <th style='background-color:#f5a742;'>Quota1</th>
			<th class='sortable' style='background-color:#f5a742;' onclick='sortTable(17)'>%X ATT.<span class='sort-icons'><span class='up-arrow'>↑</span><span class='down-arrow'>↓</span></span></th>
                <th style='background-color:#f5a742;'>QuotaX</th>
			<th class='sortable' style='background-color:#f5a742;' onclick='sortTable(19)'>%2 ATT.<span class='sort-icons'><span class='up-arrow'>↑</span><span class='down-arrow'>↓</span></span></th>
    
        
            <th style='background-color:#f5a742;'>Quota2</th>
            </tr>
            </thead>
            <tbody>"; 

    while ($row = $result->fetch_assoc()) {
        // Quote
        $quota1 = isset($row['odd1']) && is_numeric($row['odd1']) ? number_format($row['odd1'], 2) : '0.00';
        $quotaX = isset($row['oddx']) && is_numeric($row['oddx']) ? number_format($row['oddx'], 2) : '0.00';
        $quota2 = isset($row['odd2']) && is_numeric($row['odd2']) ? number_format($row['odd2'], 2) : '0.00';
        $quota1X = isset($row['odd1X']) && is_numeric($row['odd1X']) ? number_format($row['odd1X'], 2) : '0.00';
        $quotaX2 = isset($row['oddX2']) && is_numeric($row['oddX2']) ? number_format($row['oddX2'], 2) : '0.00';
        $quota12 = isset($row['odd12']) && is_numeric($row['odd12']) ? number_format($row['odd12'], 2) : '0.00';

        // Dati statistici
        $posizionecasa = $row['classifica_casa'];
        $posizioneospite = $row['classifica_ospite'];
        $punticasa = $row['punti_casa'];
        $puntiospite = $row['punti_ospite'];
        $GCAS_casa = $row['GCAS_casa'];
        $VCAS_casa = $row['VCAS_casa'];
        $XCAS_casa = $row['XCAS_casa'];
        $SCAS_casa = $row['SCAS_casa'];

        $GFCS_ospite = $row['GFCS_ospite'];
        $VFCS_ospite = $row['VFCS_ospite'];
        $XFCS_ospite = $row['XFCS_ospite'];
        $SFCS_ospite = $row['SFCS_ospite'];

        // Stato di forma
        $recent_form_casa = $row['recent_form_casa'];
        $recent_form_ospite = $row['recent_form_ospite'];

        // Calcola i punteggi di forma
        $punteggio_casa = calcolaPunteggioForma($recent_form_casa);
        $punteggio_ospite = calcolaPunteggioForma($recent_form_ospite);

        // Calcolo delle probabilità con l'aggiustamento per stato di forma
        $p_vittoria_casa = ($VCAS_casa + $SFCS_ospite) / ($GCAS_casa + $GFCS_ospite) * 100;
        $p_pareggio = ($XCAS_casa + $XFCS_ospite) / ($GCAS_casa + $GFCS_ospite) * 100;
        $p_vittoria_ospite = ($SCAS_casa + $VFCS_ospite) / ($GCAS_casa + $GFCS_ospite) * 100;

        // Aggiustamento delle probabilità in base ai punteggi di forma
        $adjustment_factor_casa = 1 + ($punteggio_casa / 10);  // Aggiustiamo di un fattore in base al punteggio
        $adjustment_factor_ospite = 1 + ($punteggio_ospite / 10);  // Aggiustiamo di un fattore in base al punteggio

        $p_vittoria_casa *= $adjustment_factor_casa;
        $p_vittoria_ospite *= $adjustment_factor_ospite;

        // Normalizzazione
        $total_probability = $p_vittoria_casa + $p_pareggio + $p_vittoria_ospite;
        if ($total_probability > 0) {
            $p_vittoria_casa = ($p_vittoria_casa / $total_probability) * 100;
            $p_pareggio = ($p_pareggio / $total_probability) * 100;
            $p_vittoria_ospite = ($p_vittoria_ospite / $total_probability) * 100;
        }
        
        // Nel ciclo while dove generi la tabella:
        $normalized_home = normalizeTeamName($row['squadra_casa']);
        $normalized_away = normalizeTeamName($row['squadra_ospite']);

        $logo_home = "loghi/" . $normalized_home . ".png";
        $logo_away = "loghi/" . $normalized_away . ".png";

        // Calcolo delle probabilità dalle quote
        $prob_vittoria_casa = ($quota1 > 0) ? (1 / $quota1) * 100 : 0;
        $prob_pareggio = ($quotaX > 0) ? (1 / $quotaX) * 100 : 0;
        $prob_vittoria_ospite = ($quota2 > 0) ? (1 / $quota2) * 100 : 0;

        // Normalizzazione delle probabilità per le quote
        $totale_prob_quote = $prob_vittoria_casa + $prob_pareggio + $prob_vittoria_ospite;
        if ($totale_prob_quote > 0) {
            $prob_vittoria_casa = ($prob_vittoria_casa / $totale_prob_quote) * 100;
            $prob_pareggio = ($prob_pareggio / $totale_prob_quote) * 100;
            $prob_vittoria_ospite = ($prob_vittoria_ospite / $totale_prob_quote) * 100;
        }
        
        // Calcolo delle medie
        $media1 = ($p_vittoria_casa + $prob_vittoria_casa) / 2;
        $mediaX = ($p_pareggio + $prob_pareggio) / 2;
        $media2 = ($p_vittoria_ospite + $prob_vittoria_ospite) / 2;
        $mesi = [
            '01' => 'Gen', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mag', '06' => 'Giu', '07' => 'Lug', '08' => 'Ago',
            '09' => 'Set', '10' => 'Ott', '11' => 'Nov', '12' => 'Dic'
        ];

        $data = strtotime($row['data_partita']);
        $giorno = date('d', $data);
        $mese_num = date('m', $data);
        $ora = date('H:i', $data);
        $mese = $mesi[$mese_num];

        // Mostra i dati nella tabella
        echo "<tr>
            <td>" . $giorno . " " . $mese . " " . $ora . "</td>
            <td><img src='loghi/" . htmlspecialchars($row['campionato']) . ".png' width='40' height='40'/></td>
            
            <td data-label='Link'>";
        if (!empty($row['link'])) {
            echo "<a href='" . htmlspecialchars($row['link']) . "' target='_blank'>
                    <img src='./flash.png' style='width: 22px; height: 22px; vertical-align: middle;' alt='Link Aggiuntivo' />
                </a>";
        }
        echo "</td>
            <td class='team-logo' data-label='Logo'>
                <img src='" . htmlspecialchars($logo_home) . "' alt='" . htmlspecialchars($row['squadra_casa']) . " logo'>
            </td>

            <td class='team-logo' data-label='Logo'>" . $row['squadra_casa'] . "</td>
            <td class='team-logo' data-label='Logo'>" . $posizionecasa . " &#176; (" . $punticasa . " pt.) [" . $recent_form_casa . "]</td>

            <td class='team-logo' data-label='Logo'>
                <img src='" . htmlspecialchars($logo_away) . "' alt='" . htmlspecialchars($row['squadra_casa']) . " logo'>
            </td>
            <td>" . $row['squadra_ospite'] . "</td>
            <td>" . $posizioneospite . " &#176; (" . $puntiospite . " pt.) [" . $recent_form_ospite . "]</td>
            <td>" . number_format($prob_vittoria_casa, 2) . "%</td>
            <td>" . number_format($prob_pareggio, 2) . "%</td>
            <td>" . number_format($prob_vittoria_ospite, 2) . "%</td>
            <td>" . number_format($p_vittoria_casa, 2) . "%</td>
            <td>" . number_format($p_pareggio, 2) . "%</td>
            <td>" . number_format($p_vittoria_ospite, 2) . "%</td>

            <td>" . number_format($media1, 2) . "%</td>
			<td>" . $quota1 . "</td>
            <td>" . number_format($mediaX, 2) . "%</td>
			<td>" . $quotaX . "</td>
            <td>" . number_format($media2, 2) . "%</td>
            <td>" . $quota2 . "</td>
        </tr>";
    }

    echo "</tbody></table></div>";
} else {
    echo "Nessun risultato trovato.";
}

$conn->close();
?>
<div style="text-align: center; font-size: 13px; font-weight: bold;">
  Note: %1 ALGO, %X ALGO, %2 ALGO indicano le probabilità calcolate dal nostro algoritmo e dalla classifica; %1 BOOK, %X BOOK, %2 BOOK derivano dalle quote bookmaker; %1 ATT.↑, %X ATT.↑, %2 ATT.↑ indicano l'attendibilità statistica delle rispettive previsioni.
</div>


</body>
</html>