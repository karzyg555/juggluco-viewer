<?php

#
# CONFIGURATION
#
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('CET');

#
# JUGGLUCO API CONFIG
#
$url = "http://[JUGGLUCO-SERVER-URL]:[PORT]/api/v1/entries.json?token=[TOKEN]"; # dane dostępowe do juggluco serwera

$showChart = true;
$showTable = true;

$defaultInterval = 270; # odstęp czasowy pomiędzy odczytami poziomów glikemii (domyślnie 4,5 min)
$defaultCount = 55; # liczba odczytów glikemii pobranych z juggluco server za pomocą pojedynczego requesta (maksymalna liczba odczytów to 55), 
$defaultSyncTimeout = 300; # liczba sekund, po których juggluco-viewer zaraportuje błąd braku nowych odczytów (domyślnie 5 min)

$highGlucose = 170; # wartość poziomu glikemii od której wartości zostaną oznaczone kolorem żółtym
$lowGlucose = 70; # wartość poziomu glikemii od której wartości zostaną oznaczone kolorem czerwonym

$stats = array(
	'lo' => 0, 
	'hi' => 0, 
	'in' => 0
);

$gluco_values = array(
	0 => array(),
	1 => array()
);

$gluco_labels = array(
	0 => array(),
	1 => array()
);

$gluco_arrows = array(
	0 => array(),
	1 => array()
);

$libre_sensors = array();

$startTimestamp = 0;
$endTimestamp = 0;
$lastBgReading  = '';
$syncDelay = 0;

# możliwośc zmiany domyślnego odstępu czasowego pomiędzy odczytami poziomu glikemii z użyciem parametru "interval" w pasku URL
if( isset($_GET['interval']) )
{
	$url .= "&interval=".$_GET['interval'];
	$defaultInterval = ($_GET['interval'] == 0) ? 60 : $_GET['interval'];
}

# możliwośc zmiany domyślnej liczby zwróconych odczytów poziomów glikemii z użyciem parametru "count" w pasku URL
if( isset($_GET['count']) )
{
	$url .= "&count=".$_GET['count'];
}
else{
	$url .= "&count=$defaultCount";
}

# SET TIME LIMIT
$timestampLimit = time()*1000-(2*$defaultCount*$defaultInterval*1000);

# możliwość ukrycia wykresu z użyciem parametru "chart=false" w pasku URL
if( isset($_GET['chart']) )
{
	$showChart = ($_GET['chart'] == "true");
}

# możliwość ukrycia tabeli z odczytami poziomów glikemii z użyciem parametru "table=false" w pasku URL
if( isset($_GET['table']) )
{
	$showTable = ($_GET['table'] == "true");
}

#
# JUGGLUCO API CALL
#
$raw_juggluco = file_get_contents($url . "&find[date][\$gte]=".$timestampLimit);
$json_juggluco = json_decode($raw_juggluco);

$counter = 1;
$tableHTML = '<table><tr><th>No.</th><th>Glukoza</th><th>Trend</th><th>Delta</th><th>Data pomiaru</th><th>Sensor</th></tr>';
foreach($json_juggluco as $juggluco_entry){
	
	if( $counter == 1 )
	{
		$endTimestamp = $juggluco_entry->date;
		$lastBgReading = $juggluco_entry->sgv;
		$syncDelay = round((time()*1000-$endTimestamp)/1000);
	}
	
	$arrow = get_bg_arrow($juggluco_entry->direction);
	
	$tableHTML .= "<tr><td>".$counter++."</td><td>".$juggluco_entry->sgv."</td><td class='arrow'>&#x$arrow;</td><td>".$juggluco_entry->delta."</td><td>".date("Y-m-d H:i:s", substr($juggluco_entry->date,0,-3))."</td><td>".substr($juggluco_entry->_id,0,11)."</td></tr>";	
	
	array_unshift($gluco_values[0], $juggluco_entry->sgv);	
	array_unshift($gluco_labels[0], $juggluco_entry->date);					
	array_unshift($gluco_arrows[0], $arrow);
		
	if(! in_array(substr($juggluco_entry->_id,0,11), $libre_sensors) )
	{
		array_unshift($libre_sensors, substr($juggluco_entry->_id,0,11));
	}
	
	$startTimestamp = $juggluco_entry->date;
	
	if($juggluco_entry->sgv > $highGlucose){
		$stats['hi']++;
	}
	elseif($juggluco_entry->sgv < $lowGlucose){
		$stats['lo']++;
	}
	else{
		$stats['in']++;
	}
}

$tableHTML .= '</table>';

$minSyncDelay = round($syncDelay / 60);

$HTML = '<!DOCTYPE html>
<html>
<head>'.($syncDelay > $defaultSyncTimeout ? '<link rel="icon" href="icon/favicon.ico" type="image/x-icon">' : '').'
<title>&#x'.end($gluco_arrows[0]).'; '.$lastBgReading.' mg/dl at ' . date("H:i:s", $endTimestamp / 1000) . ' (' . ($minSyncDelay).' min '.($syncDelay-$minSyncDelay*60).' sec ago)</title>
<meta http-equiv="refresh" content="60">
<style>

body {
	text-align: left;
	font-family: arial, sans-serif;
	font-weight: bold;
}

table {
	margin-top: 10px;
	font-family: arial, sans-serif;
	border-collapse: collapse;
	width: 100%;
	font-weight: normal;
}

td, th {
	border: 1px solid #dddddd;
	text-align: center;
	padding: 8px;
}

tr:nth-child(even) {
	background-color: #dddddd;
}

tr:nth-of-type(2) {
	border-top: 2px solid #000000;
	color: #ff0000;
	font-weight: bold;
	font-size: 110%;
}

td.arrow {
	font-size: 130%;
	font-weight: bold;
}

#countdown{
	color: #ff0000;
	padding-bottom: 5px;
}

#gluco-chart-div{
	height: 400px;
}

</style>

<script>
var countDown = 60;

function countdown() {
    setInterval(function () {
        if (countDown == 0) {
            return;
        }
        countDown--;
        document.getElementById("countdown").innerHTML = countDown;
        return countDown;
    }, 1000);
}

countdown();
</script>

</head>
<body>
Auto refresh in: <span id="countdown"></span> seconds';

if( $showChart )
{
	$HTML .= '<div id="gluco-chart-div"><canvas id="gluco-chart"></canvas></div>';
}

if( $showTable )
{
	$HTML .= $tableHTML;
}

// ChartJS
if( $showChart ) 
{ 	
	// JUGGLUCO API CALL FOR YESTERDAY DATA
	$url .= "&find[date][\$lte]=".($endTimestamp - 86400000);	
	$raw_juggluco = file_get_contents($url);
	$json_juggluco = json_decode($raw_juggluco);
	
	foreach($json_juggluco as $juggluco_entry){		
		array_unshift($gluco_values[1], $juggluco_entry->sgv);		
		array_unshift($gluco_labels[1], $juggluco_entry->date + 86400000);
		array_unshift($gluco_arrows[1], get_bg_arrow($juggluco_entry->direction));
	}
	
	$counter--;
	
	$stats['avg'] = round((array_sum($gluco_values[0])/$counter));
	$stats['sd'] = round(stand_deviation($gluco_values[0]));
	$stats['variability'] = round($stats['sd']/$stats['avg']*100);

	$legend1 = "Range: " . gmdate("H:i:s", ($endTimestamp - $startTimestamp)/1000) . " ($counter#), step: ". ceil($defaultInterval / 60) . ' min';
	
	$legend2 = "Avg: ".$stats['avg']." mg/dl, Sd: ".$stats['variability']."% (".$stats['sd']."), [ in: ".round($stats['in']/$counter*100)."%, hi: ".round($stats['hi']/$counter*100)."%, lo: ".round($stats['lo']/$counter*100)."% ]";
	
	$legend3 = "Sensor: " . implode(',', $libre_sensors) . ", Last sync: " . date("Y-m-d H:i:s", $endTimestamp / 1000);
	
	$labels = array_merge($gluco_labels[0], $gluco_labels[1]);
	asort($labels);
	
	$HTML .= '
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.0/chartjs-adapter-moment.js"></script>
	
	<script>
	
	  const ctx = document.getElementById(\'gluco-chart\');
	  const lowGlucose = ' . $lowGlucose . ';
	  const highGlucose = ' . $highGlucose . ';
		
	  new Chart(ctx, {
		type: \'line\',		
		data: {		  
		  labels: ['.implode(',', $labels).'],
		  datasets: [{
			label: \'today\',			
			data: ['.build_data_json($gluco_values[0], $gluco_labels[0], $gluco_arrows[0]).'],
			borderWidth: 2,			
			borderColor: "rgba(50,205,50,0.5)",
			backgroundColor: "rgba(50,205,50,0.5)",
			fill: true,
			segment: {		
				borderColor: (ctx) => (ctx.p1.parsed.y > highGlucose ? \'rgba(255,255,0,0.5)\' : ctx.p1.parsed.y < lowGlucose ? \'rgba(255,0,0,0.5)\' : \'rgba(50,205,50,0.5)\'),
				backgroundColor: (ctx) => (ctx.p1.parsed.y > highGlucose ? \'rgba(255,255,0,0.5)\' : ctx.p1.parsed.y < lowGlucose ? \'rgba(255,0,0,0.5)\' : \'rgba(50,205,50,0.5)\')				
			},
			pointBackgroundColor: function(context) {
				var index = context.dataIndex
				var value = context.dataset.data[index]
				return value.y > highGlucose ? \'rgba(255,255,0,0.5)\' : value < lowGlucose ? \'rgba(255,0,0,0.5)\' : \'rgba(50,205,50,0.5)\'
			},
			pointBorderColor: function(context) {
				var index = context.dataIndex
				var value = context.dataset.data[index]
				return value.y > highGlucose ? \'rgba(255,255,0,0.5)\' : value < lowGlucose ? \'rgba(255,0,0,0.5)\' : \'rgba(50,205,50,0.5)\'
			},
		  },{
			label: \'yesterday\',
			data: ['.build_data_json($gluco_values[1], $gluco_labels[1], $gluco_arrows[1]).'],
			borderWidth: 2,			
			borderColor: "rgba(0,0,0,1)",
			backgroundColor: "rgba(255,255,255,0.5)",
		  }]
		},
		options: {
			maintainAspectRatio: false,
			plugins: {
				subtitle: {
					display: true,
					font: {weight: \'bold\'},
					color: \'rgba(0,0,0,1)\',
					text: [\''.$legend1.'\', \''.$legend2.'\', \''.$legend3.'\']	
				},
				tooltip: {
					enabled: true,
					usePointStyle: true,
					callbacks: { 
					
						// To change title in tooltip
						title: (data) => { 
							const parsedDate = data[0].datasetIndex == 0 ? data[0].parsed.x : (data[0].parsed.x-86400000);
							const newDate = new Date(parsedDate);
							const formattedNewDate = newDate.toLocaleString(\'sv-SE\');
							return formattedNewDate
						}, 

						// To change label in tooltip
						label: (data) => {
							var index = data.dataIndex
							var value = data.dataset.data[index]
							var unicode = parseInt(value.z, 16)
							return data.parsed.y + \' mg/dl \' + String.fromCodePoint(unicode)
						},
						
						// To change label color in tooltip
						labelColor: (data) => {
																				
							return {
								borderColor: data.parsed.y > highGlucose ? \'rgba(255,255,0,1)\' : data.parsed.y < lowGlucose ? \'rgba(255,0,0,1)\' : \'rgba(50,205,50,1)\',
								backgroundColor: data.parsed.y > highGlucose ? \'rgba(255,255,0,0.5)\' : data.parsed.y < lowGlucose ? \'rgba(255,0,0,0.5)\' : \'rgba(50,205,50,0.5)\',
								borderWidth: 2,
								borderDash: [2, 2],
								borderRadius: 2
							}
						}
					},
				}				
			},			
			scales: {
				x: {
					type: \'time\',										
					title: {
						display: true,
						text: "Date",
						font: {            
							weight: "bold"
						}
					},
					time: {
						displayFormats: { 
							second: \'HH:mm:ss\',
							minute: \'HH:mm\',
							hour: \'HH:mm\',
							day: \'dd-MMM\',
							month: \'MMM-yyyy\',
							year: \'yyyy\'
						}							
					},
					min: '.$startTimestamp.',
					max: '.$endTimestamp.'					
				},
				y: {
					title: {
						display: true,
						text: "mg/dl",
						font: {        
							weight: "bold"
						}
					}        
				}
			}			
		}
	  });
	</script>';
}	

$HTML .= '</body></html>';

// Print HTML
echo $HTML;

function stand_deviation($arr) 
{ 
	$num_of_elements = count($arr); 
	  
	$variance = 0.0; 
	  
	// calculating mean using array_sum() method 
	$average = array_sum($arr)/$num_of_elements; 
	  
	foreach($arr as $i) 
	{ 
		// sum of squares of differences between  
		// all numbers and means. 
		$variance += pow(($i - $average), 2); 
	} 
	  
	return (float)sqrt($variance/$num_of_elements); 
} 

function build_data_json($sgv, $labels, $arrows)
{
	$json = '';

	for($i = 0; $i < count($sgv); $i++)
	{
		$json .= ($i == 0 ? '' : ',');
		$json .= '{x: '.$labels[$i].', y: '.$sgv[$i].', z: "'.$arrows[$i].'"}';
	}
	
	return $json;
}

function get_bg_arrow($sgvDirection)
{
	$arrow = '2192';
	
	switch ($sgvDirection) 
	{
		case "SingleUp": $arrow = "2191"; break;						
		case "SingleDown": $arrow = "2193"; break;			
		case "FortyFiveUp": $arrow = "2197"; break;			
		case "FortyFiveDown": $arrow = "2198"; break;			
		case "DoubleUp": $arrow = "21C8"; break;			
		case "DoubleDown":	$arrow = "21CA"; break;				
	}
	
	return $arrow;
}

?>
