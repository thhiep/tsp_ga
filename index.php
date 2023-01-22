<?php
/*
Genetic algorithm for solving travelling salesman problem with berlin52 dataset
By: Hong Hiep Trinh 
Email: trinhhong.hiep@gmail.com
Date: 2022-12

Best achieved result is around 7544 after ~20,000-40,000 generations.
Best known optimal result so far is 7542.

7544.366=0-21-30-17-2-16-20-41-6-1-29-22-19-49-28-15-45-43-33-34-35-38-39-36-37-47-23-4-14-5-3-24-11-27-26-25-46-12-13-51-10-50-32-42-9-8-7-40-18-44-31-48
*/

include("tsp_ga.inc.php");

set_time_limit(30*60);
ini_set('memory_limit', '1024M');

header("content-type:text/plain");
echo "*** Solving travelling salesman problem using genetic algorithm by trinhhong.hiep@gmail.com ***\n\n";
$tsp = new TSP_GA();
$tsp->readDataFile("berlin52.tsp");
/*
$a=explode("-","7-3-1-8-2-4-6-5");;
$b=explode("-","7-3-6-8-2-4-1-5");;
$tsp->crossover($a,$b);
exit;
*/
$t0=time();
$lucky = $tsp->fitness($tsp->lucky_solution());
//echo sprintf("\n%.2f = %s",$lucky,implode("-",$tsp->lucky_solution()));
//exit;
do{
	//$tsp->init_population(50);
	//$lucky = $tsp->fitness($tsp->population[0]);
	$t1=time();
	$nochange=0;
	$last=100000000;
	do {
		unset($r);
		$r = $tsp->iterate(50,5,20000);
		if ($r["total"]<$last){
			$nochange=0;
		} else {
			$nochange++;
		}
		$last = $r["total"];
		if ($r["total"]<$lucky){
			$known = $tsp->readKnownSolutions();
			if (!isset($known[$r["path"]])){
				file_put_contents($tsp->filename.".known","\n".$r["total"]."=".$r["path"],FILE_APPEND | LOCK_EX);
				print_r($r);
			}
		}
		$p=array(
			"y"=>implode(",",$tsp->scores),
			"yt"=>"Total distance",
			"xt"=>"Iterations",
			"t"=>"TSP solver"
		);
		$f = fopen(time().'.csv', 'w');
		for($i=0;$i<count($tsp->scores);$i++){
			fputcsv($f,[$i,$tsp->scores[$i]]);
		}
		fclose($f);
		$parsed1 = (time()-$t1)/60;
	} while (!$r["bingo"] && $parsed1<5 && $nochange<10);
	
	$parsed0 = (time()-$t0)/60;
} while ($parsed0<10);
