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
        if (basename($_SERVER['PHP_SELF']) != 'angolimobile.php') {
            header('Location: angolimobile.php');
            exit;
        }
    } else {
        // Se il dispositivo è un PC
        if (basename($_SERVER['PHP_SELF']) != 'angoli.php') {
            header('Location: angoli.php');
            exit;
        }
    }
}		
$campionato = isset($_GET['campionato']) ? $_GET['campionato'] : 'all';
$limit = isset($_GET['limit']) && $_GET['limit'] !== 'all' ? (int)$_GET['limit'] : 5;
$soglia = isset($_SESSION['soglia']) ? $_SESSION['soglia'] : '9.5';
$data = isset($_GET['data']) && !empty($_GET['data']) ? $_GET['data'] : date('Y-m-d');
// Controlla se è stata inviata una richiesta POST per aggiornare la soglia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_soglia') {
    if (isset($_POST['soglia'])) {
        $_SESSION['soglia'] = $_POST['soglia'];
        echo "Soglia aggiornata a: " . htmlspecialchars($_POST['soglia']);
    } else {
        echo "Soglia non trovata.";
    }
    exit; // Termina il processamento per la richiesta AJAX
}

// Recupera la variabile soglia dalla sessione o usa un valore di default


// Funzione per calcolare la funzione di distribuzione cumulativa normale
function normal_cdf($z) {
    return (1.0 + erf($z / sqrt(2))) / 2.0;
}

// Funzione per calcolare la funzione errore
function erf($x) {
    // Costanti
    $a1 =  0.254829592;
    $a2 = -0.284496736;
    $a3 =  1.421413741;
    $a4 = -1.453152027;
    $a5 =  1.061405429;
    $p =  0.3275911;

    // Calcolo
    $sign = ($x >= 0) ? 1 : -1;
    $x = abs($x) / sqrt(2.0);
    $t = 1.0 / (1.0 + $p * $x);
    $tau = $t * ($a1 + $t * ($a2 + $t * ($a3 + $t * ($a4 + $t * $a5))));
    return $sign * (1.0 - $tau * exp(-$x * $x));
}
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
// Gestione della variabile giornata
$giornata = isset($_GET['giornata']) ? intval($_GET['giornata']) : 4; 
// Ottieni la data odierna
//$data = isset($_GET['data']) ? $_GET['data'] : (new DateTime())->format('Y-m-d');
$data_oggi = date('Y-m-d');

// Usa la variabile $data_oggi nel tuo codice
//echo "Data odierna: " . htmlspecialchars($data_oggi);

// Query per ottenere i dati delle partite della giornata specificata
// Costruisci la query di base
$sql_partite = "
SELECT 
    p.squadra_casa, 
    p.squadra_ospite, 
    p.partita, 
    p.data_partita,
    p.campionato,
	  p.link,
    sc.id AS squadra_casa_id, 
    so.id AS squadra_ospite_id,
    q.u_7_5, 
    q.o_7_5, 
    q.u_8_5, 
    q.o_8_5, 
    q.u_9_5, 
    q.o_9_5, 
    q.u_10_5, 
    q.o_10_5, 
    q.u_11_5, 
    q.o_11_5,
	q.angolo1,
	q.angoloX,
	q.angolo2,
	 ch.overall_rank AS squadra_casa_rank,
    ch.overall_points AS squadra_casa_points,
    ch.overall_recent_form AS squadra_casa_recent_form,
    co.overall_rank AS squadra_ospite_rank,
    co.overall_points AS squadra_ospite_points,
    co.overall_recent_form AS squadra_ospite_recent_form
FROM 
    partiteseriea p
JOIN 
    squadre sc ON p.squadra_casa COLLATE utf8mb4_general_ci = sc.nome COLLATE utf8mb4_general_ci
JOIN 
    squadre so ON p.squadra_ospite COLLATE utf8mb4_general_ci = so.nome COLLATE utf8mb4_general_ci
JOIN 
    quoteidealbet q ON p.partita COLLATE utf8mb4_general_ci = q.partita COLLATE utf8mb4_general_ci
JOIN 
    classifiche ch ON p.squadra_casa COLLATE utf8mb4_general_ci = ch.squadra COLLATE utf8mb4_general_ci
JOIN 
    classifiche co ON p.squadra_ospite COLLATE utf8mb4_general_ci = co.squadra COLLATE utf8mb4_general_ci
WHERE 1 = 1
";

// Aggiungi il filtro campionato se non è "all"
if ($campionato !== 'all') {
    $sql_partite .= " AND p.campionato = ?";
}

// Aggiungi il filtro data solo se è stata selezionata una data
if (!empty($data)) {
    if ($data == 'tutte') {
        // Se è stata selezionata "Tutte", imposta il range da oggi a 4 giorni
        $today = new DateTime();
        $fourDaysLater = new DateTime();
        $fourDaysLater->modify('+4 days');
        
        // Aggiungi la condizione per il range di date
        $sql_partite .= " AND DATE(p.data_partita) BETWEEN ? AND ?";
        $params[] = $today->format('Y-m-d');
        $params[] = $fourDaysLater->format('Y-m-d');
    } else {
        // Se è stata selezionata una data specifica, usa quella data
        $sql_partite .= " AND DATE(p.data_partita) = ?";
        $params[] = $data; // Aggiungi la data selezionata come parametro
    }
} else {
    // Se non è stata selezionata nessuna data, mostra solo le partite del giorno
    $today = new DateTime();  // Data odierna
    $sql_partite .= " AND DATE(p.data_partita) = ?";
    $params[] = $today->format('Y-m-d');
}

// Default sorting order for "Data"
$data_sort_order = "ASC";

// Check if 'sort_data' query parameter is set
if (isset($_GET['sort_data'])) {
    $data_sort_order = ($_GET['sort_data'] === 'desc') ? "DESC" : "ASC";
}

// Aggiungi ordinamento con il filtro di ordinamento dinamico per "data_partita"
$sql_partite .= " ORDER BY p.data_partita $data_sort_order, p.squadra_casa, p.squadra_ospite";

// Debug query (da commentare in produzione)
// echo "Query SQL: " . $sql_partite; 

// Prepara la query
$stmt_partite = $conn->prepare($sql_partite);
if ($stmt_partite === false) {
    // Stampa l'errore
    echo "Errore nella preparazione della query: " . $conn->error;
    exit;
}

// Gestisci i parametri in base a campionato e data
if ($campionato !== 'all' && $data == 'tutte') {
    // Bind campionato e range di date (oggi a 4 giorni)
    $stmt_partite->bind_param("sss", $campionato, $params[0], $params[1]);
} elseif ($campionato !== 'all' && !empty($data)) {
    // Bind campionato e data specifica
    $stmt_partite->bind_param("ss", $campionato, $params[0]);
} elseif ($campionato !== 'all') {
    // Bind solo il campionato
    $stmt_partite->bind_param("s", $campionato);
} elseif (!empty($data) && $data == 'tutte') {
    // Bind solo il range di date (oggi a 4 giorni)
    $stmt_partite->bind_param("ss", $params[0], $params[1]);
} elseif (!empty($data)) {
    // Bind solo la data specifica
    $stmt_partite->bind_param("s", $params[0]);
}

// Esegui la query
if ($data == 'tutte') {
    // Usa la data corrente
    $prima_data = new DateTime();  // Prende la data corrente
    
    // Aggiungi 4 giorni alla data corrente
    $fourDaysLater = clone $prima_data;
    $fourDaysLater->modify('+4 days');
    
    // Aggiungi la condizione per il range di date
    $sql_partite .= " AND DATE(p.data_partita) BETWEEN ? AND ?";
    $params[] = $prima_data->format('Y-m-d');
    $params[] = $fourDaysLater->format('Y-m-d');
    
    // Stampa per debug
    //echo "Range di date: " . $prima_data->format('Y-m-d') . " - " . $fourDaysLater->format('Y-m-d');
}
// Prepara la query
$stmt_partite->execute();
$result_partite = $stmt_partite->get_result();

?>
<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Statistiche Calcio Scommesse Sportive del giorno <?php echo $data; ?> </title>
    <style>
        /* Stile generale */
						   
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background-color: #ffffff;
			   
        }

        /* Contenitore per h1 e hamburger menu */
        .header {
				
    display: flex;
    align-items: center;
    justify-content: space-between; /* Spaziatura tra titolo e menu */
    padding: 10px 20px;
    background-color: #ffffff;
    border-bottom: 2px solid #ddd; /* Aggiungi una linea sottile */
    position: sticky; /* Rende il contenuto "sticky" */
    top: 0; /* Sempre visibile in cima */
    z-index: 1000; /* Assicurati che sia sopra altri contenuti */
										  
}
.menu-container {
    display: flex;
    align-items: center;
    gap: 10px; /* Spaziatura tra icona e menu hamburger */
}
        /* Testo centrato */
        .header h1 {
    font-size: 24px;
    color: #333;
    margin: 0;
    text-align: center; /* Centra il testo */
    flex-grow: 1; /* Occupa lo spazio disponibile */
				 
}
.home-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: transform 0.3s ease;
}

.home-icon:hover {
    transform: scale(1.2); /* Effetto di ingrandimento al passaggio del mouse */
}
       .menu-hamburger {
    position: relative;
    margin-left: auto; /* Spinge il bottone verso destra */
			   
}

								 
.menu-hamburger button {
    font-size: 24px;
				
				 
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    transition: transform 0.3s ease;
}

							
.menu-hamburger button:hover {
    transform: rotate(90deg); /* Animazione rotazione */
}
.menu-hamburger button.active {
    transform: rotate(90deg); /* Applicare la rotazione quando la classe "active" è presente */
}
#toggle-filters {
    transition: transform 0.3s ease; /* Per animare la rotazione */
				
}

#toggle-filters.active {
    transform: rotate(90deg); /* Ruota il pulsante quando la classe "active" è presente */
												 
}
        /* Layout mobile: stili per schermi con larghezza max di 768px */
        @media (max-width: 768px) {
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                background-color: #ffffff;
                overflow-x: hidden;
            }

            h1 {
                font-size: 18px;
                color: #333;
                margin: 15px 0;
                display: inline-block; /* Permette di affiancare l'icona */
            }

            .icon-container {
                display: inline-block; /* Posizionamento accanto all'H1 */
                vertical-align: middle;
                margin-left: 10px;
            }

            .icon-container button {
                font-size: 24px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
                transition: transform 0.3s ease;
            }

            .icon-container button:hover {
                transform: rotate(90deg); /* Animazione rotazione */
            }

            .controls-wrapper {
                display: block;
                margin: 5px auto;
                padding: 10px;
                text-align: center;
							
				 
            }

            .control-container {
                margin-bottom: 15px;
                width: 100%;
					  
                display: flex;
                justify-content: center;
				 
				  
					   
					   
            }

            select {
                width: 100%;
                padding: 12px;
                font-size: 16px;
                margin: 5px 0;
                border-radius: 8px;
                border: 1px solid #ccc;
                background-color: #fff;
                box-sizing: border-box;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23999'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 15px;
                transition: all 0.3s ease;
            }

            select:hover {
                border-color: #5e9ed6;
                background-color: #f8f8f8;
				 
										
            }

            select:focus {
                outline: none;
			   
				
                border-color: #0056b3;
                box-shadow: 0 0 8px rgba(0, 86, 179, 0.5);
						   
            }

            .filters-container {
                position: fixed; /* Fissa il contenitore alla finestra del browser */
                top: 10%; /* Posiziona il contenitore al 10% dall'alto della finestra */
                padding: 16px; /* Aggiunge padding interno */
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                transition: max-height 0.3s ease, opacity 0.3s ease;
                max-height: 0;
                opacity: 0;
                overflow: hidden;
				 z-index: 1000; /* Garantisce che appaia sopra altri elementi */
            } 
            .filters-container.show {
                max-height: 500px;
                opacity: 1;
            }


            /* Stili per le tabelle mobile */
            .table-desktop {
                display: none;
            }

            .table-mobile {
				margin-top: 5px;
                display: block;
                width: 100%;
                margin: 0 auto;
            }

            .table-mobile table, .table-mobile thead {
                display: none;
					
				   
            }

            .table-mobile table, .table-mobile tbody, .table-mobile tr, .table-mobile td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            .table-mobile tr {
                margin-bottom: 15px;
					
            }

            .table-mobile td {
                text-align: center;
                padding: 7px;
                border: none;
                position: relative;
                background-color: white;
                color: black;
            }

            .table-mobile td::before {
                content: attr(data-label);
                position: relative;
                font-weight: bold;
                display: block;
                background-color: #999999;
                color: white;
                padding: 7px;
                margin-bottom: 5px;
                text-align: center;
            }

            .team-logo {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                flex-wrap: nowrap;
                margin: 0 auto;
            }

            .team-logo img {
                max-height: 30px;
                width: auto;
                height: auto;
                margin: 0 auto;
            }

            .sticky-soglia {
                position: sticky;
                top: 0;
                background-color: white;
                z-index: 1000;
                padding: 5px 0;
            }
        }
 .table-mobile .separator {
    display: inline-block;
    width: 1px;
    height: 1em;
    background-color: #000;
    margin: 0 15px; /* Spaziatura attorno alla linea */
    vertical-align: middle;
  }

 .scroll-to-top {
    position: fixed; /* Posizionato rispetto alla finestra */
    bottom: 20px; /* Distanza dal bordo inferiore */
    right: 20px; /* Distanza dal bordo destro */
    width: 50px; /* Larghezza del pulsante */
    height: 50px; /* Altezza del pulsante */
    background-color: rgba(0, 123, 255, 0.7); /* Blu con trasparenza (70% opacità) */
    color: #ffffff; /* Colore del testo (freccia) */
    border: none; /* Nessun bordo */
    border-radius: 50%; /* Pulsante rotondo */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Ombra */
    font-size: 20px; /* Dimensione della freccia */
    cursor: pointer; /* Mostra il cursore a mano */
    display: none; /* Nascondi il pulsante inizialmente */
    z-index: 1000; /* Sopra altri elementi */
    transition: opacity 0.3s ease; /* Animazione per apparizione/scomparsa */
}

.scroll-to-top:hover {
    background-color: #0056b3; /* Cambia colore al passaggio del mouse */
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

    </script>
		 
</head>
<body>
<div class="header">
    <h1>Analisi Angoli 🚩</h1>
    <div class="menu-container">
        <!-- Icona casa -->
        <a href="index.php" class="home-icon" title="Torna alla home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="#333">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
        </a>
        <!-- Menu hamburger -->
        <div class="menu-hamburger">
            <button id="toggle-filters" onclick="toggleFilters()">&#9776;</button>
        </div>
    </div>
</div>
<div class="controls-wrapper" style="margin-top: 2px; margin-bottom: 1px;"> <!-- ridotto lo spazio sopra -->

    <!-- Contenitore del pannello dei filtri -->
    <div class="filters-container" id="filters-container">
        <div class="control-container">
            <select id='page-select' onchange='changePage()'>
				<option value="" disabled selected>Mercato: Corner 🚩🚩</option>
                <option value='angoli.php?soglia=7.5'>Corner 🚩</option>
		        <option value='cartellini.php?soglia=4.5'>Cartellini 🟨</option>
                <option value='tiriinporta.php?soglia=7.5'>Tiri in Porta 🥅</option>
				<option value='tiritotali.php?soglia=26.5'>Tiri Totali 🎯</option>
                <option value='falli.php?soglia=24.5'>Falli Totali 🛑</option>
                <option value='fuorigioco.php?soglia=2.5'>Fuori gioco 🚫🏃‍</option>
           </select>
        </div>



<div class="control-container">
    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
      <select name="campionato" id="campionato" onchange="this.form.submit()">
            <option value="all" <?php if ($campionato == 'all') echo 'selected'; ?>>Campionati</option>
            <option value="SerieA" <?php if ($campionato == 'SerieA') echo 'selected'; ?>>Serie A</option>
            <option value="SerieB" <?php if ($campionato == 'SerieB') echo 'selected'; ?>>Serie B</option>
            <option value="PremierLeague" <?php if ($campionato == 'PremierLeague') echo 'selected'; ?>>Premier League</option>
            <option value="Liga" <?php if ($campionato == 'Liga') echo 'selected'; ?>>Liga</option>
            <option value="Ligue1" <?php if ($campionato == 'Ligue1') echo 'selected'; ?>>Ligue 1</option>
            <option value="Bundesliga" <?php if ($campionato == 'Bundesliga') echo 'selected'; ?>>Bundesliga</option>
			<option value="Eredivise" <?php if ($campionato == 'Eredivise') echo 'selected'; ?>>Eredivise</option>
			<option value="liga-portugal" <?php if ($campionato == 'liga-portugal') echo 'selected'; ?>>Liga-Portugal</option>
			<option value="SuperLigTurchia" <?php if ($campionato == 'SuperLigTurchia') echo 'selected'; ?>>SuperLig Turchia</option>
            <option value="champions" <?php if ($campionato == 'champions') echo 'selected'; ?>>Champions League</option>
            <option value="EuropaLeague" <?php if ($campionato == 'EuropaLeague') echo 'selected'; ?>>Europa League</option>
            <option value="CoppaItalia" <?php if ($campionato == 'CoppaItalia') echo 'selected'; ?>>Coppa Italia</option>
            <option value="Mondiali" <?php if ($campionato == 'Mondiali') echo 'selected'; ?>>Mondiali</option>
            <option value="NationLeague" <?php if ($campionato == 'NationLeague') echo 'selected'; ?>>Nations League</option>
       </select>
 

        

<select name="data" id="date-select" onchange="this.form.submit()">
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

       
<select name="limit" id="limit" onchange="this.form.submit()">
    <option value="5" <?php if (isset($_GET['limit']) && $_GET['limit'] == '5') echo 'selected'; ?>>
        Ultime partite: 5
    </option>

    <?php
   
    // Opzioni da 1 a 5
    for ($i = 1; $i <= 4; $i++) {
        $selected = (isset($_GET['limit']) && $_GET['limit'] == $i) ? 'selected' : '';
        echo "<option value='$i' $selected>Ultime partite: $i</option>";
    }

    // Opzione per 10
    $selected = (isset($_GET['limit']) && $_GET['limit'] == '10') ? 'selected' : '';
    echo "<option value='10' $selected>Ultime partite: 10</option>";
	
	 // Aggiungi l'opzione 'Tutte' come predefinita se non è stato selezionato altro
    $selected = (isset($_GET['limit']) && $_GET['limit'] == '38') ? 'selected' : '';
    echo "<option value='38' $selected>Ultime partite: Tutte</option>";

    ?>
</select>
        <!-- Hidden submit button -->
        <input type="submit" value="Filtra" style="display:none;">
    </form>
</div>



<div class="control-container">
    <select id="soglia-select" name="soglia" onchange="changeSoglia()">
        <option value="" disabled selected hidden>Partite precedenti</option>
        <option value="7.5" <?php if ($soglia == 7.5) echo 'selected'; ?>>7.5</option>
        <option value="8.5" <?php if ($soglia == 8.5) echo 'selected'; ?>>8.5</option>
        <option value="9.5" <?php if ($soglia == 9.5) echo 'selected'; ?>>9.5</option>
        <option value="10.5" <?php if ($soglia == 10.5) echo 'selected'; ?>>10.5</option>
        <option value="11.5" <?php if ($soglia == 11.5) echo 'selected'; ?>>11.5</option>
    </select>
</div>
 </div>
<script>
   


function toggleFilters() {
    const filtersContainer = document.getElementById('filters-container');
    const button = document.getElementById('toggle-filters'); // Seleziona il pulsante
    
    console.log("Toggling filters..."); // Log per il debug
    filtersContainer.classList.toggle('show'); // Alterna la visibilità del contenitore dei filtri
    
    // Alterna la classe 'active' sul pulsante per la rotazione
    button.classList.toggle('active');
    
    // Controllo se la classe 'show' è stata aggiunta
    if (filtersContainer.classList.contains('show')) {
        console.log('Filters are now visible');
        
        // Scorri per portare i filtri nella visualizzazione
        filtersContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        console.log('Filters are now hidden');
    }
}
function updateDate() {
    var date = document.getElementById('date-select').value;
    if (date !== '0') {
        // Fai qualcosa con la data selezionata
        console.log("Data selezionata:", date);
        // Ad esempio, puoi inviare la data a una funzione PHP tramite AJAX o ricaricare la pagina con un parametro GET
        window.location.href = '?data=' + encodeURIComponent(date);
			
    }
}
function formatDate(dataPartita) {
    const date = new Date(dataPartita);
    const options = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleString('it-IT', options).replace('.', ''); // Rimuove eventuali punti dal mese
}					   
</script>
<script>
function openPopup(squadraCasa, squadraOspite) {
    // Crea l'URL con i parametri squadra_casa e squadra_ospite
    var url = "angolipopup1.php?squadra_casa=" + encodeURIComponent(squadraCasa) + "&squadra_ospite=" + encodeURIComponent(squadraOspite);
    
    // Apre il popup con dimensioni specifiche
    window.open(url, "PopupSquadra", "width=650,height=550,scrollbars=yes");
}

</script>																								  
</body>
</html>

<?php

// Assicurati che la funzione calculate_percentage() sia dichiarata solo una volta nel tuo codice
// Function to calculate the percentage
function calculate_percentage($numerator, $denominator) {
    if ($denominator == 0) {
        return 0; // Avoid division by zero
    }
    return ($numerator / $denominator) * 100;
}

// Function to determine color based on percentage
function determine_color($percentage) {
    if ($percentage < 60) {
        return 'red';
    } elseif ($percentage < 80) {
        return 'yellow';
    } else {
        return 'green';
    }
}


// Query per le statistiche sugli angoli
$sql_tiri = "
    SELECT DISTINCT
        c.nome AS competizione,
        s_home.nome AS home_team,
        s_away.nome AS away_team,
        st.home_value AS home_tiri,
        st.away_value AS away_tiri
    FROM statistiche st
    JOIN competizioni c ON st.competizione_id = c.id
    JOIN squadre s_home ON st.home_team_id = s_home.id
    JOIN squadre s_away ON st.away_team_id = s_away.id
    WHERE (st.home_team_id = ? OR st.away_team_id = ?)
    AND st.categoria = 'Calci d\'angolo'
    ORDER BY c.nome
";


// Ciclo sulle partite
if ($result_partite->num_rows > 0) {
    while ($row = $result_partite->fetch_assoc()) {
        $logo_home = "loghi/" . str_replace(' ', ' ', $row['squadra_casa']) . ".png";
        $logo_away = "loghi/" . str_replace(' ', ' ', $row['squadra_ospite']) . ".png";
$u7_5 = @number_format($row['u_7_5'], 2, '.', '');
$o7_5 = @number_format($row['o_7_5'], 2, '.', '');
$u8_5 = @number_format($row['u_8_5'], 2, '.', '');
$o8_5 = @number_format($row['o_8_5'], 2, '.', '');
$u9_5 = @number_format($row['u_9_5'], 2, '.', '');
$o9_5 = @number_format($row['o_9_5'], 2, '.', '');
$u10_5 = @number_format($row['u_10_5'], 2, '.', '');
$o10_5 = @number_format($row['o_10_5'], 2, '.', '');
$u11_5 = @number_format($row['u_11_5'], 2, '.', '');
$o11_5 = @number_format($row['o_11_5'], 2, '.', '');
		$angolo1 = $row['angolo1'];
		$angoloX = $row['angoloX'];
		$angolo2 = $row['angolo2'];
		$campionato = $row['campionato'];
		// Crea la stringa per la cella della tabella in base al valore di $soglia
$quotaString = "";
$quanticasa_ospite = "";
$quanticasa_ospitemin = "";

// SQL query template for statistics
// Set default value for LIMIT (no limit if 'Tutti' is selected)

$sql_quanti = "
SELECT 
    CONCAT(SUM(quante7_5_gt), '/', COUNT(*)) AS count_quante7_5_gt,
    CONCAT(SUM(quante7_5_lt), '/', COUNT(*)) AS count_quante7_5_lt,
    CONCAT(SUM(quante8_5_gt), '/', COUNT(*)) AS count_quante8_5_gt,
    CONCAT(SUM(quante8_5_lt), '/', COUNT(*)) AS count_quante8_5_lt,
    CONCAT(SUM(quante9_5_gt), '/', COUNT(*)) AS count_quante9_5_gt,
    CONCAT(SUM(quante9_5_lt), '/', COUNT(*)) AS count_quante9_5_lt,
    CONCAT(SUM(quante10_5_gt), '/', COUNT(*)) AS count_quante10_5_gt,
    CONCAT(SUM(quante10_5_lt), '/', COUNT(*)) AS count_quante10_5_lt,
    CONCAT(SUM(quante11_5_gt), '/', COUNT(*)) AS count_quante11_5_gt,
    CONCAT(SUM(quante11_5_lt), '/', COUNT(*)) AS count_quante11_5_lt
FROM (
    SELECT *, 
           (home_angoli + away_angoli) AS total_angoli,
           CASE WHEN (home_angoli + away_angoli) > 7.5 THEN 1 ELSE 0 END AS quante7_5_gt,
           CASE WHEN (home_angoli + away_angoli) < 7.5 THEN 1 ELSE 0 END AS quante7_5_lt,
           CASE WHEN (home_angoli + away_angoli) > 8.5 THEN 1 ELSE 0 END AS quante8_5_gt,
           CASE WHEN (home_angoli + away_angoli) < 8.5 THEN 1 ELSE 0 END AS quante8_5_lt,
           CASE WHEN (home_angoli + away_angoli) > 9.5 THEN 1 ELSE 0 END AS quante9_5_gt,
           CASE WHEN (home_angoli + away_angoli) < 9.5 THEN 1 ELSE 0 END AS quante9_5_lt,
           CASE WHEN (home_angoli + away_angoli) > 10.5 THEN 1 ELSE 0 END AS quante10_5_gt,
           CASE WHEN (home_angoli + away_angoli) < 10.5 THEN 1 ELSE 0 END AS quante10_5_lt,
           CASE WHEN (home_angoli + away_angoli) > 11.5 THEN 1 ELSE 0 END AS quante11_5_gt,
           CASE WHEN (home_angoli + away_angoli) < 11.5 THEN 1 ELSE 0 END AS quante11_5_lt
    FROM (
        SELECT DISTINCT
            c.nome AS competizione,
            s_home.nome AS home_team,
            s_away.nome AS away_team,
            st.home_value AS home_angoli,
            st.away_value AS away_angoli,
			st.data_ora As data,
            st.id
        FROM statistiche st
        JOIN competizioni c ON st.competizione_id = c.id
        JOIN squadre s_home ON st.home_team_id = s_home.id
        JOIN squadre s_away ON st.away_team_id = s_away.id
        WHERE (st.home_team_id = ? OR st.away_team_id = ?)
        AND st.categoria = 'Calci d\'angolo'
		ORDER BY st.data_ora DESC
        ";

// Add LIMIT only if a specific limit is selected
if ($limit) {
    $sql_quanti .= " LIMIT ?";
}

$sql_quanti .= ") AS ordered_results
) AS final_results;
";

// Query for the home team
if ($limit) {
    $stmt_quanti_casa = $conn->prepare($sql_quanti);
    $stmt_quanti_casa->bind_param('iii', $row['squadra_casa_id'], $row['squadra_casa_id'], $limit);
} else {
    $stmt_quanti_casa = $conn->prepare($sql_quanti);
    $stmt_quanti_casa->bind_param('ii', $row['squadra_casa_id'], $row['squadra_casa_id']);
}

// Execute and fetch results for the home team
$stmt_quanti_casa->execute();
$result_quanti_casa = $stmt_quanti_casa->get_result();
$data_casa = $result_quanti_casa->fetch_assoc();

// Free result and close statement for the home team
$stmt_quanti_casa->free_result();
$stmt_quanti_casa->close();

// Prepare the statement with or without limit for the away team
if ($limit) {
    $stmt_quanti_ospite = $conn->prepare($sql_quanti);
    $stmt_quanti_ospite->bind_param('iii', $row['squadra_ospite_id'], $row['squadra_ospite_id'], $limit);
} else {
    $stmt_quanti_ospite = $conn->prepare($sql_quanti);
    $stmt_quanti_ospite->bind_param('ii', $row['squadra_ospite_id'], $row['squadra_ospite_id']);
}

// Execute the query and fetch results for the away team
$stmt_quanti_ospite->execute();
$result_quanti_ospite = $stmt_quanti_ospite->get_result();
$data_ospite = $result_quanti_ospite->fetch_assoc();

// Free result and close statement for the away team
$stmt_quanti_ospite->free_result();
$stmt_quanti_ospite->close();


// Assign results to variables for home team
$quanti_casa7_5 = $data_casa['count_quante7_5_gt'];
$quanti_casa7_5min = $data_casa['count_quante7_5_lt'];
$quanti_casa8_5 = $data_casa['count_quante8_5_gt'];
$quanti_casa8_5min = $data_casa['count_quante8_5_lt'];
$quanti_casa9_5 = $data_casa['count_quante9_5_gt'];
$quanti_casa9_5min = $data_casa['count_quante9_5_lt'];
$quanti_casa10_5 = $data_casa['count_quante10_5_gt'];
$quanti_casa10_5min = $data_casa['count_quante10_5_lt'];
$quanti_casa11_5 = $data_casa['count_quante11_5_gt'];
$quanti_casa11_5min = $data_casa['count_quante11_5_lt'];

// Assign results to variables for away team
$quanti_ospite7_5 = $data_ospite['count_quante7_5_gt'];
$quanti_ospite7_5min = $data_ospite['count_quante7_5_lt'];  
$quanti_ospite8_5 = $data_ospite['count_quante8_5_gt'];
$quanti_ospite8_5min = $data_ospite['count_quante8_5_lt']; 
$quanti_ospite9_5 = $data_ospite['count_quante9_5_gt'];
$quanti_ospite9_5min = $data_ospite['count_quante9_5_lt'];  
$quanti_ospite10_5 = $data_ospite['count_quante10_5_gt'];
$quanti_ospite10_5min = $data_ospite['count_quante10_5_lt'];  
$quanti_ospite11_5 = $data_ospite['count_quante11_5_gt'];
$quanti_ospite11_5min = $data_ospite['count_quante11_5_lt']; 


// 7.5
list($quanti_casa_numerator, $quanti_casa_denominator) = explode('/', $data_casa['count_quante7_5_gt']);
list($quanti_ospite_numerator, $quanti_ospite_denominator) = explode('/', $data_ospite['count_quante7_5_gt']);
list($quanti_casa_numerator1, $quanti_casa_denominator1) = explode('/', $data_casa['count_quante7_5_lt']);
list($quanti_ospite_numerator1, $quanti_ospite_denominator1) = explode('/', $data_ospite['count_quante7_5_lt']);

$totalover7_5_numerator = $quanti_casa_numerator + $quanti_ospite_numerator;
$totalover7_5_denominator = $quanti_casa_denominator + $quanti_ospite_denominator;
$percentage_over7_5 = calculate_percentage($totalover7_5_numerator, $totalover7_5_denominator);
$percentage_over7_5 = number_format($percentage_over7_5, 2);
$color_over7_5 = determine_color($percentage_over7_5);
$totalover7_5 = $totalover7_5_numerator . "/" . $totalover7_5_denominator . " (" . $percentage_over7_5 . "% <span style='color: $color_over7_5;'>●</span>)";

$totalunder7_5_numerator = $quanti_casa_numerator1 + $quanti_ospite_numerator1;
$totalunder7_5_denominator = $quanti_casa_denominator1 + $quanti_ospite_denominator1;
$percentage_under7_5 = calculate_percentage($totalunder7_5_numerator, $totalunder7_5_denominator);
$percentage_under7_5 = number_format($percentage_under7_5, 2);
$color_under7_5 = determine_color($percentage_under7_5);
$totalunder7_5 = $totalunder7_5_numerator . "/" . $totalunder7_5_denominator . " (" . $percentage_under7_5 . "% <span style='color: $color_under7_5;'>●</span>)";

// 8.5
list($quanti_casa_numerator, $quanti_casa_denominator) = explode('/', $data_casa['count_quante8_5_gt']);
list($quanti_ospite_numerator, $quanti_ospite_denominator) = explode('/', $data_ospite['count_quante8_5_gt']);
list($quanti_casa_numerator1, $quanti_casa_denominator1) = explode('/', $data_casa['count_quante8_5_lt']);
list($quanti_ospite_numerator1, $quanti_ospite_denominator1) = explode('/', $data_ospite['count_quante8_5_lt']);

$totalover8_5_numerator = $quanti_casa_numerator + $quanti_ospite_numerator;
$totalover8_5_denominator = $quanti_casa_denominator + $quanti_ospite_denominator;
$percentage_over8_5 = calculate_percentage($totalover8_5_numerator, $totalover8_5_denominator);
$percentage_over8_5 = number_format($percentage_over8_5, 2);
$color_over8_5 = determine_color($percentage_over8_5);
$totalover8_5 = $totalover8_5_numerator . "/" . $totalover8_5_denominator . " (" . $percentage_over8_5 . "% <span style='color: $color_over8_5;'>●</span>)";

$totalunder8_5_numerator = $quanti_casa_numerator1 + $quanti_ospite_numerator1;
$totalunder8_5_denominator = $quanti_casa_denominator1 + $quanti_ospite_denominator1;

$percentage_under8_5 = calculate_percentage($totalunder8_5_numerator, $totalunder8_5_denominator);
$percentage_under8_5 = number_format($percentage_under8_5, 2);
$color_under8_5 = determine_color($percentage_under8_5);
$totalunder8_5 = $totalunder8_5_numerator . "/" . $totalunder8_5_denominator . " (" . $percentage_under8_5 . "% <span style='color: $color_under8_5;'>●</span>)";

// 9.5
list($quanti_casa_numerator, $quanti_casa_denominator) = explode('/', $data_casa['count_quante9_5_gt']);
list($quanti_ospite_numerator, $quanti_ospite_denominator) = explode('/', $data_ospite['count_quante9_5_gt']);
list($quanti_casa_numerator1, $quanti_casa_denominator1) = explode('/', $data_casa['count_quante9_5_lt']);
list($quanti_ospite_numerator1, $quanti_ospite_denominator1) = explode('/', $data_ospite['count_quante9_5_lt']);

$totalover9_5_numerator = $quanti_casa_numerator + $quanti_ospite_numerator;
$totalover9_5_denominator = $quanti_casa_denominator + $quanti_ospite_denominator;
$percentage_over9_5 = calculate_percentage($totalover9_5_numerator, $totalover9_5_denominator);
$percentage_over9_5 = number_format($percentage_over9_5, 2);
$color_over9_5 = determine_color($percentage_over9_5);
$totalover9_5 = $totalover9_5_numerator . "/" . $totalover9_5_denominator . " (" . $percentage_over9_5 . "% <span style='color: $color_over9_5;'>●</span>)";

$totalunder9_5_numerator = $quanti_casa_numerator1 + $quanti_ospite_numerator1;
$totalunder9_5_denominator = $quanti_casa_denominator1 + $quanti_ospite_denominator1;
$percentage_under9_5 = calculate_percentage($totalunder9_5_numerator, $totalunder9_5_denominator);
$percentage_under9_5 = number_format($percentage_under9_5, 2);
$color_under9_5 = determine_color($percentage_under9_5);
$totalunder9_5 = $totalunder9_5_numerator . "/" . $totalunder9_5_denominator . " (" . $percentage_under9_5 . "% <span style='color: $color_under9_5;'>●</span>)";

// 10.5
list($quanti_casa_numerator, $quanti_casa_denominator) = explode('/', $data_casa['count_quante10_5_gt']);
list($quanti_ospite_numerator, $quanti_ospite_denominator) = explode('/', $data_ospite['count_quante10_5_gt']);
list($quanti_casa_numerator1, $quanti_casa_denominator1) = explode('/', $data_casa['count_quante10_5_lt']);
list($quanti_ospite_numerator1, $quanti_ospite_denominator1) = explode('/', $data_ospite['count_quante10_5_lt']);

$totalover10_5_numerator = $quanti_casa_numerator + $quanti_ospite_numerator;
$totalover10_5_denominator = $quanti_casa_denominator + $quanti_ospite_denominator;
$percentage_over10_5 = calculate_percentage($totalover10_5_numerator, $totalover10_5_denominator);
$percentage_over10_5 = number_format($percentage_over10_5, 2); // Formatta a due decimali
$color_over10_5 = determine_color($percentage_over10_5);
$totalover10_5 = $totalover10_5_numerator . "/" . $totalover10_5_denominator . " (" . $percentage_over10_5 . "% <span style='color: $color_over10_5;'>●</span>)";

$totalunder10_5_numerator = $quanti_casa_numerator1 + $quanti_ospite_numerator1;
$totalunder10_5_denominator = $quanti_casa_denominator1 + $quanti_ospite_denominator1;
$percentage_under10_5 = calculate_percentage($totalunder10_5_numerator, $totalunder10_5_denominator);
$percentage_under10_5 = number_format($percentage_under10_5, 2);
$color_under10_5 = determine_color($percentage_under10_5);
$totalunder10_5 = $totalunder10_5_numerator . "/" . $totalunder10_5_denominator . " (" . $percentage_under10_5 . "% <span style='color: $color_under10_5;'>●</span>)";

// 11.5
list($quanti_casa_numerator, $quanti_casa_denominator) = explode('/', $data_casa['count_quante11_5_gt']);
list($quanti_ospite_numerator, $quanti_ospite_denominator) = explode('/', $data_ospite['count_quante11_5_gt']);
list($quanti_casa_numerator1, $quanti_casa_denominator1) = explode('/', $data_casa['count_quante11_5_lt']);
list($quanti_ospite_numerator1, $quanti_ospite_denominator1) = explode('/', $data_ospite['count_quante11_5_lt']);

$totalover11_5_numerator = $quanti_casa_numerator + $quanti_ospite_numerator;
$totalover11_5_denominator = $quanti_casa_denominator + $quanti_ospite_denominator;

$percentage_over11_5 = calculate_percentage($totalover11_5_numerator, $totalover11_5_denominator);
$percentage_over11_5 = number_format($percentage_over11_5, 2); // Formatta a due decimali
$color_over11_5 = determine_color($percentage_over11_5);

$totalover11_5 = $totalover11_5_numerator . "/" . $totalover11_5_denominator . " (" . $percentage_over11_5 . "%) <span style='color: $color_over11_5;'>●</span>";

// Somma numeratori e denominatori per "under"
$totalunder11_5_numerator = $quanti_casa_numerator1 + $quanti_ospite_numerator1;
$totalunder11_5_denominator = $quanti_casa_denominator1 + $quanti_ospite_denominator1;

// Calcola la percentuale per "under"
$percentage_under11_5 = calculate_percentage($totalunder11_5_numerator, $totalunder11_5_denominator);
$percentage_under11_5 = number_format($percentage_under11_5, 2); // Formatta a due decimali
$color_under11_5 = determine_color($percentage_under11_5);

// Imposta il valore finale per "under"
$totalunder11_5 = $totalunder11_5_numerator . "/" . $totalunder11_5_denominator . " (" . $percentage_under11_5 . "% <span style='color: $color_under11_5;'>●</span>)";
$totaluo ="";


switch ($soglia) {
    case 7.5:
        $quotaString = $u7_5 . " | " . $o7_5;
		$quanticasa_ospite = $quanti_casa7_5 . " | " . $quanti_ospite7_5;
		$quanticasa_ospitemin =  $quanti_casa7_5min . " | " . $quanti_ospite7_5min;
		$totaluo = $totalunder7_5 . " | " . $totalover7_5;
        break;
    case 8.5:
        $quotaString = $u8_5 . " | " . $o8_5;
		$quanticasa_ospite = $quanti_casa8_5 . " | " . $quanti_ospite8_5;
		$quanticasa_ospitemin =  $quanti_casa8_5min . " | " . $quanti_ospite8_5min;
				$totaluo = $totalunder8_5 . " | " . $totalover8_5;
        break;
    case 9.5:
        $quotaString = $u9_5 . " | " . $o9_5;
		$quanticasa_ospite = $quanti_casa9_5 . " | " . $quanti_ospite9_5;
		$quanticasa_ospitemin =  $quanti_casa9_5min . " | " . $quanti_ospite9_5min;
				$totaluo = $totalunder9_5 . " | " . $totalover9_5;
        break;
    case 10.5:
        $quotaString = $u10_5 . " | " . $o10_5;
		$quanticasa_ospite = $quanti_casa10_5 . " | " . $quanti_ospite10_5;
		$quanticasa_ospitemin =  $quanti_casa10_5min . " | " . $quanti_ospite10_5min;
				$totaluo = $totalunder10_5 . " | " . $totalover10_5;
        break;
    case 11.5:
        $quotaString = $u11_5 . " | " . $o11_5;
		$quanticasa_ospite = $quanti_casa11_5 . " | " . $quanti_ospite11_5;
		$quanticasa_ospitemin =  $quanti_casa11_5min . " | " . $quanti_ospite11_5min;
				$totaluo = $totalunder11_5 . " | " . $totalover11_5;
        break;
    default:
        $quotaString = "Soglia non valida";
        break;
}
		
         // Statistiche della squadra di casa
        $stmt_angoli_casa = $conn->prepare($sql_tiri);
        $stmt_angoli_casa->bind_param('ii', $row['squadra_casa_id'], $row['squadra_casa_id']);
        $stmt_angoli_casa->execute();
        $result_angoli_casa = $stmt_angoli_casa->get_result();
		// Calcolo delle statistiche aggiuntive per la squadra casa
$sql_totali_casa = "
    SELECT 
        SUM(CASE WHEN st.home_team_id = ? THEN st.home_value ELSE 0 END) AS angoli_fatti_in_casa,
        SUM(CASE WHEN st.home_team_id = ? THEN st.away_value ELSE 0 END) AS angoli_subiti_in_casa,
        SUM(CASE WHEN st.away_team_id = ? THEN st.home_value ELSE 0 END) AS angoli_subiti_fuori_casa,
        SUM(CASE WHEN st.away_team_id = ? THEN st.away_value ELSE 0 END) AS angoli_fatti_fuori_casa,
        COUNT(DISTINCT CASE WHEN st.home_team_id = ? THEN st.id ELSE NULL END) AS partite_in_casa,
        COUNT(DISTINCT CASE WHEN st.away_team_id = ? THEN st.id ELSE NULL END) AS partite_fuori_casa 
    FROM 
        (SELECT * FROM statistiche 
         WHERE categoria = 'Calci d\'angolo' 
         AND (home_team_id = ? OR away_team_id = ?)
         ORDER BY data_ora DESC"; // Ordina in base a data_ora decrescente
         
// Aggiungiamo il limite, se specificato
if ($limit) {
    $sql_totali_casa .= " LIMIT ?";
}

$sql_totali_casa .= ") AS st";

// Prepariamo lo statement
if ($limit) {
    $stmt_totali_casa = $conn->prepare($sql_totali_casa);
    $stmt_totali_casa->bind_param('iiiiiiiii', $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $limit);
} else {
    $stmt_totali_casa = $conn->prepare($sql_totali_casa);
    $stmt_totali_casa->bind_param('iiiiiiii', $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id'], $row['squadra_casa_id']);
}

// Eseguiamo e recuperiamo i risultati
$stmt_totali_casa->execute();
$result_totali_casa = $stmt_totali_casa->get_result();
$row_totali_casa = $result_totali_casa->fetch_assoc();
        // Calcolo delle medie
        $partite_in_casa = $row_totali_casa['partite_in_casa'];
        $partite_fuori_casa = $row_totali_casa['partite_fuori_casa'];
        $partite_totali = $partite_in_casa + $partite_fuori_casa;
        $media_angoli_fatti_in_casa = $partite_in_casa > 0 ? $row_totali_casa['angoli_fatti_in_casa'] / $partite_in_casa : 0;
        $media_angoli_subiti_in_casa = $partite_in_casa > 0 ? $row_totali_casa['angoli_subiti_in_casa'] / $partite_in_casa : 0; 
        $media_angoli_fatti_fuori_casa = $partite_fuori_casa > 0 ? $row_totali_casa['angoli_fatti_fuori_casa'] / $partite_fuori_casa : 0; 
        $media_angoli_subiti_fuori_casa = $partite_fuori_casa > 0 ? $row_totali_casa['angoli_subiti_fuori_casa'] / $partite_fuori_casa : 0;
        // Totali tiri 
        $angoli_fatti_totali = $row_totali_casa['angoli_fatti_in_casa'] + $row_totali_casa['angoli_fatti_fuori_casa'];
        $angoli_subiti_totali = $row_totali_casa['angoli_subiti_in_casa'] + $row_totali_casa['angoli_subiti_fuori_casa'];
        $media_angoli_fatti_totali = number_format($angoli_fatti_totali / $partite_totali, 2);
        $media_angoli_subiti_totali = number_format($angoli_subiti_totali / $partite_totali, 2);
        $media_angoli_totali = number_format($media_angoli_fatti_totali + $media_angoli_subiti_totali, 2);
		// Statistiche della squadra ospite
        $stmt_angoli_ospite = $conn->prepare($sql_tiri);
        $stmt_angoli_ospite->bind_param('ii', $row['squadra_ospite_id'], $row['squadra_ospite_id']);
        $stmt_angoli_ospite->execute();
        $result_angoli_ospite = $stmt_angoli_ospite->get_result();

        // Calcolo delle statistiche aggiuntive per la squadra ospite
$sql_totali_ospite = "
    SELECT
        SUM(CASE WHEN st.home_team_id = ? THEN st.home_value ELSE 0 END) AS angoli_fatti_in_casa,
        SUM(CASE WHEN st.home_team_id = ? THEN st.away_value ELSE 0 END) AS angoli_subiti_in_casa,
        SUM(CASE WHEN st.away_team_id = ? THEN st.home_value ELSE 0 END) AS angoli_subiti_fuori_casa,
        SUM(CASE WHEN st.away_team_id = ? THEN st.away_value ELSE 0 END) AS angoli_fatti_fuori_casa,
        COUNT(DISTINCT CASE WHEN st.home_team_id = ? THEN st.id ELSE NULL END) AS partite_in_casa,
        COUNT(DISTINCT CASE WHEN st.away_team_id = ? THEN st.id ELSE NULL END) AS partite_fuori_casa
    FROM 
        (SELECT * FROM statistiche 
         WHERE categoria = 'Calci d\'angolo' 
         AND (home_team_id = ? OR away_team_id = ?)
         ORDER BY data_ora DESC"; // Ordina per data_ora decrescente
         
// Aggiungiamo il limite, se specificato
if ($limit) {
    $sql_totali_ospite .= " LIMIT ?";
}

$sql_totali_ospite .= ") AS st";

// Preparazione della query
if ($limit) {
    $stmt_totali_ospite = $conn->prepare($sql_totali_ospite);
    $stmt_totali_ospite->bind_param('iiiiiiiii', $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $limit);
} else {
    $stmt_totali_ospite = $conn->prepare($sql_totali_ospite);
    $stmt_totali_ospite->bind_param('iiiiiiii', $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id'], $row['squadra_ospite_id']);
}

// Esecuzione e recupero risultati
$stmt_totali_ospite->execute();
$result_totali_ospite = $stmt_totali_ospite->get_result();
$row_totali_ospite = $result_totali_ospite->fetch_assoc();
		//Ospite
        // Calcolo delle medie
        $partite_in_casa = $row_totali_ospite['partite_in_casa'];
        $partite_fuori_casa = $row_totali_ospite['partite_fuori_casa'];
        $partite_totali = $partite_in_casa + $partite_fuori_casa;

        $media_angoli_fatti_in_casa = $partite_in_casa > 0 ? $row_totali_ospite['angoli_fatti_in_casa'] / $partite_in_casa : 0;
        $media_angoli_subiti_in_casa = $partite_in_casa > 0 ? $row_totali_ospite['angoli_subiti_in_casa'] / $partite_in_casa : 0; 
        $media_angoli_fatti_fuori_casa = $partite_fuori_casa > 0 ? $row_totali_ospite['angoli_fatti_fuori_casa'] / $partite_fuori_casa : 0; 
        $media_angoli_subiti_fuori_casa = $partite_fuori_casa > 0 ? $row_totali_ospite['angoli_subiti_fuori_casa'] / $partite_fuori_casa : 0;

        // Totali tiri 
        $angoli_fatti_totali_ospite = $row_totali_ospite['angoli_fatti_in_casa'] + $row_totali_ospite['angoli_fatti_fuori_casa'];
        $angoli_subiti_totali_ospite = $row_totali_ospite['angoli_subiti_in_casa'] + $row_totali_ospite['angoli_subiti_fuori_casa'];
		$media_angoli_fatti_totali_ospite = number_format($angoli_fatti_totali_ospite / $partite_totali, 2);
		$media_angoli_subiti_totali_ospite = number_format($angoli_subiti_totali_ospite / $partite_totali, 2);
        $media_angoli_totali_ospite = number_format($media_angoli_fatti_totali_ospite + $media_angoli_subiti_totali_ospite, 2);
		
		$media_angoli_totali_entrambe = number_format(($media_angoli_totali + $media_angoli_totali_ospite) / 2, 2);
		// $media_totali_ospite = $media_fatti_fiorentina + $media_subiti_fiorentina;
$media_totali_combinata = ($media_angoli_totali_entrambe);
$delta = $media_totali_combinata - $soglia;

$delta_emoji = "🔴"; 
if (abs($delta) >= 1.5 && abs($delta) < 3) {
    $delta_emoji = "🟡"; // Giallo
} elseif (abs($delta) >= 3) {
    $delta_emoji = "🟢"; // Verde
}

//AGGIUNTE PER 1X2
// Calcolo della differenza
$differenza = $media_angoli_fatti_totali - $media_angoli_fatti_totali_ospite;
$suggerisci1x2angoli = '';

if (abs($differenza) <= 1) {
    // Se la differenza è minore o uguale a 1 in valore assoluto
    $suggerisci1x2angoli = 'Δ (' . abs(round($differenza, 2)) . ') |  <span style="color:red;">●</span> | NO BET';
} elseif ($differenza >= 1) {
    if ($differenza >= 4) {
        // Se la differenza è maggiore o uguale a 4
        $suggerisci1x2angoli = 'Δ (' . round($differenza, 2) . ') | 1 <span style="color:green;">●</span> | quota1 (' 
                            . $angolo1 . ')';
    } else {
        // Se la differenza è positiva e maggiore di 2 ma minore di 4
        $suggerisci1x2angoli = 'Δ (' . round($differenza, 2) . ') | 1 <span style="color:yellow;">●</span> | quota1 (' 
                            . $angolo1 . ')';
    }
} elseif ($differenza <= -1) {
    if (abs($differenza) >= 4) {
        // Se la differenza è minore o uguale a -4
        $suggerisci1x2angoli = 'Δ (' . abs(round($differenza, 2)) . ') | 2 <span style="color:green;">●</span> | quota2 (' 
                            . $angolo2 . ')';
    } else {
        // Se la differenza è negativa e minore di -2 ma maggiore di -4
        $suggerisci1x2angoli = 'Δ (' . abs(round($differenza, 2)) . ') | 2 <span style="color:yellow;">●</span> | quota2 (' 
                            . $angolo2 . ')';
    }
}



//
// Deviazione standard
$deviazione_standard = 2;

// Soglia di angoli
//$soglia = 9.5;

// Calcola il valore Z
$z = ($soglia - $media_totali_combinata) / $deviazione_standard;

// Calcola la probabilità che il numero di angoli sia maggiore della soglia
$probabilita_superare_soglia = 1 - normal_cdf($z);
  $link = htmlspecialchars($row['link']);
   $data_partita = urlencode($row['data_partita']);

// Calcola il complementare
	
echo "<div class='table-desktop'><table>
    <thead>
        <tr>
            <th>Data</th>
			<th>Lega</th>
            <th>Logo Casa</th>
            <th>Squadra Casa</th>
            <th>Media Fatti</th>
            <th>Media Subiti</th>
            <th>Media Totali</th>
            <th>Logo Ospite</th>
            <th>Squadra Ospite</th>
            <th>Media Fatti</th>
            <th>Media Subiti</th>
            <th>Media Totali</th>
            <th>Media Tot 1&2</th>
			<th>Angoli 1X2</th>
		    <th>% quanti U. O. totali $soglia</th>
			
			<th>% AFFIDABILITÀ U | O : $soglia</th>
			<th>QUOTE UNDER & OVER $soglia</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td data-label='Data'>" . htmlspecialchars($Dataformattata) . "</td>
			<td class='team-logo' data-label='team-logo'>
                    <img src='loghi/" . htmlspecialchars($campionato) . ".png' alt='" . htmlspecialchars($campionato) . "' />
                </td>
			<td class='team-logo' data-label='Logo Casa'><img src='" . htmlspecialchars($logo_home) . "' alt='" . htmlspecialchars($row['squadra_casa']) . " logo'></td>
            <td data-label='Squadra Casa'>" . htmlspecialchars($row['squadra_casa']) . "</td>
            <td data-label='Media Fatti'>" . $media_angoli_fatti_totali . "</td>
            <td data-label='Media Subiti'>" . $media_angoli_subiti_totali . "</td>
            <td data-label='Media Totali'>" . $media_angoli_totali . "</td>
            <td class='team-logo' data-label='Logo Ospite'><img src='" . htmlspecialchars($logo_away) . "' alt='" . htmlspecialchars($row['squadra_ospite']) . " logo'></td>
            <td data-label='Squadra Ospite'>" . htmlspecialchars($row['squadra_ospite']) . "</td>
            <td data-label='Media Fatti'>" . $media_angoli_fatti_totali_ospite . "</td>
            <td data-label='Media Subiti'>" . $media_angoli_subiti_totali_ospite . "</td>
            <td data-label='Media Totali'>" . $media_angoli_totali_ospite . "</td>
            <td data-label='Media Tot 1&2'>" . htmlspecialchars($media_angoli_totali_entrambe) . " (Δ: " . number_format($delta, 2) . ") " . $delta_emoji . "</td>
            
			<td data-label='Angoli 1X2'>$suggerisci1x2angoli</td>
			<td data-label='Quanti O'>$totaluo</td>
			

			<td data-label='UNDER $soglia - % WIN - OVER $soglia'>";


// Calcola il complementare come il complemento della probabilità
$complementare_soglia = 1 - $probabilita_superare_soglia;

// Crea un array con i mesi in italiano
$mesi = [
    'Jan' => 'Gen', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
    'May' => 'Mag', 'Jun' => 'Giu', 'Jul' => 'Lug', 'Aug' => 'Ago',
    'Sep' => 'Set', 'Oct' => 'Ott', 'Nov' => 'Nov', 'Dec' => 'Dic'
];
date_default_timezone_set('Europe/Rome');  // Set to your local time zone
$date1 = new DateTime($row['data_partita']);  // Create DateTime object
$month = $date1->format('M');
$italianMonth = $mesi[$month];
$Dataformattata = $date1->format('d ') . $italianMonth . $date1->format(' H:i');

//$dataOriginale = $row['data_partita'];
//     $dataFormattata = date('d M H:i', strtotime($dataOriginale));

// Logica per il complementare
if ($complementare_soglia < 0.60) {
    echo "🔴 " . number_format($complementare_soglia * 100, 2) . "%";
} elseif ($complementare_soglia < 0.75) {
    echo "🟡 " . number_format($complementare_soglia * 100, 2) . "%";
} else {
    echo "🟢 " . number_format($complementare_soglia * 100, 2) . "%";
}

// Aggiungi una linea verticale come separatore
echo " | ";
// Logica per la probabilità
if ($probabilita_superare_soglia < 0.60) {
    echo "🔴 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
} elseif ($probabilita_superare_soglia < 0.75) {
    echo "🟡 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
} else {
    echo "🟢 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
}
echo "</td>
			<td data-label='Quota'>$quotaString</td>
        </tr>
    </tbody>
</table></div>"; 

echo "<div class='table-mobile'>
    <table>
        <thead>
            <tr>
			    <th>Data</th>
                <th>Partita</th>
                 <th>Info</th>
                 

                <th>Media Fatti</th>
                <th>Media Subiti</th>
                <th>Media Totali</th>
                <th>Media Tot 1e2</th>
            </tr>
        </thead>
        <tbody>
<td data-label='Data'>
  <div style='display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 100%;'>
<img src='loghi/" . htmlspecialchars($campionato) . ".png' style='width: 30px; height: auto; alt='" . htmlspecialchars($campionato) . "' /> " . htmlspecialchars($Dataformattata) . "  <a href='./partita1.php?data=" . urlencode($row['data_partita']) . "&partita=" . urlencode($row['partita']) . "' target='_blank'>
        <!-- Immagine Info -->
        <img src='./info.png' style='width: 22px; height: 22px;' alt='Info' />
    </a>
 
 <?php if (!empty($link)): ?>
        <!-- Icona per il link aggiuntivo -->
       <a href='$link' target='_blank'>
            <img src='./flash.png' style='width: 22px; height: 22px;' alt='Link Aggiuntivo' />
        </a>
    <?php endif; ?>
	
<a href=\"javascript:void(0);\" onclick=\"openPopup('" . addslashes($row['squadra_casa']) . "', '" . addslashes($row['squadra_ospite']) . "')\">
            <img src='./stats.png' alt='" . htmlspecialchars($row['squadra_casa']) . " vs " . htmlspecialchars($row['squadra_ospite']) . "' style='width: 25px; height: 25px;'>
        </a>
	
	 </div>
	</td>

<td data-label='Partita'>
  <div style='display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 100%;'>
    " . htmlspecialchars($row['squadra_casa']) . " <img src='" . htmlspecialchars($logo_home) . "' alt='" . htmlspecialchars($row['squadra_casa']) . " logo' style='width: 30px; height: auto; margin-right: 5px;'>
<span class='separator'></span>
" . htmlspecialchars($row['squadra_ospite']) . " <img src='" . htmlspecialchars($logo_away) . "' alt='" . htmlspecialchars($row['squadra_ospite']) . " logo' style='width: 30px; height: auto; margin-left: 5px;'>
    </div>
</td>
<td data-label='Classifica e Forma'>
             " . 
    htmlspecialchars($row['squadra_casa_rank']) . '° (' .
    htmlspecialchars($row['squadra_casa_points']) . ' pt) [' .
    str_replace(',', '', htmlspecialchars($row['squadra_casa_recent_form'])) . ']' .
"   <span class='separator'></span>  
    " . htmlspecialchars($row['squadra_ospite_rank']) . '° (' .
    htmlspecialchars($row['squadra_ospite_points']) . ' pt) [' .
    str_replace(',', '', htmlspecialchars($row['squadra_ospite_recent_form'])) . ']' .
"
                </td>


				
<td data-label='Media Fatti - Subiti (Casa | Ospite)'>
  <span class='media-value'>" . htmlspecialchars($media_angoli_fatti_totali) . "</span><span class='separator'></span><span class='media-value'>" . htmlspecialchars($media_angoli_fatti_totali_ospite) . "</span>
   &nbsp; - &nbsp;
  <span class='media-value'>" . htmlspecialchars($media_angoli_subiti_totali) . "</span><span class='separator'></span><span class='media-value'>" . htmlspecialchars($media_angoli_subiti_totali_ospite) . "</span>
 
</td> 




<td data-label='Totali (Casa | Ospite) - Totale'>  
    <span class='media-value'>" . htmlspecialchars($media_angoli_totali) . "</span>
    <span class='separator'></span>
    <span class='media-value'>" . htmlspecialchars($media_angoli_totali_ospite) . "</span>
    &nbsp; = &nbsp;" . htmlspecialchars($media_angoli_totali_entrambe) . 
    " (Δ: " . number_format($delta, 2) . ") " . $delta_emoji . "</td>


				<td data-label='Under $soglia - QUOTE - Over $soglia'>$quotaString</td>
				<td data-label='Under $soglia - % WIN - Over $soglia'>";


// Calcola il complementare come il complemento della probabilità
$complementare_soglia = 1 - $probabilita_superare_soglia;


// Logica per il complementare
if ($complementare_soglia < 0.60) {
    echo "🔴 " . number_format($complementare_soglia * 100, 2) . "%";
} elseif ($complementare_soglia < 0.75) {
    echo "🟡 " . number_format($complementare_soglia * 100, 2) . "%";
} else {
    echo "🟢 " . number_format($complementare_soglia * 100, 2) . "%";
}

// Aggiungi una linea verticale come separatore
echo " | ";
// Logica per la probabilità
if ($probabilita_superare_soglia < 0.60) {
    echo "🔴 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
} elseif ($probabilita_superare_soglia < 0.75) {
    echo "🟡 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
} else {
    echo "🟢 " . number_format($probabilita_superare_soglia * 100, 2) . "%";
}



echo "<td data-label='Under $soglia - Ultime $limit - OVER $soglia'>$totaluo</td></td>
 </tr>
        </tbody>
    </table>
<div style='display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background-color: #ffffff;'>
    <hr style='width: 100%; border: none; border-top: 4px solid #D3D3D3;'>
    
</div>
 <button id='scrollToTop' class='scroll-to-top' title='Torna su'>↑</button>
<script>
// Seleziona il pulsante
const scrollToTopButton = document.getElementById('scrollToTop');

// Mostra o nasconde il pulsante in base alla posizione dello scroll
window.addEventListener('scroll', () => {
    if (window.scrollY > 200) { // Mostra il pulsante dopo aver scrollato 200px
        scrollToTopButton.style.display = 'block';
    } else {
        scrollToTopButton.style.display = 'none';
    }
});

// Scrolla verso l'alto quando il pulsante viene cliccato
scrollToTopButton.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth' // Scroll animato
    });
});
</script>
</div>";




   }
} else {
    echo "<p>Nessuna partita trovata per il giorno $data.</p>";
}

// Chiudi la connessione
$conn->close();	
?>
