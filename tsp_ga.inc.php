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

class TSP_GA {
	var $filename;
	var $cities=[];
	var $distances=[];
	var $the_lucky_solution=[];//foolest solution just by going to the next nearest city
	var $population=[];
	var $population_fitness=[];
	var $scores=[];
	var $best_solution=[];
	
	function readDataFile($f){
		$this->filename="";
		$this->cities=[];
		$lines=0;
		if (!file_exists($f)) return [];
		$handle = fopen($f, "r");
		if (!$handle) return [];
		$this->filename = $f;
		while (($line = fgets($handle)) !== false) {
			// process the line read
			$line = trim($line);
			if (!strlen($line)) continue;	//skip empty lines
			$m=[];
			if (preg_match("/^([0-9]+)\s+([0-9\.\+\-]+)\s+([0-9\.\+\-]+)\s*$/",$line,$m)){
				$i = (int)$m[1];
				$x = (double)$m[2];
				$y = (double)$m[3];
				$this->cities[] = array($x,$y);
				$lines++;
			}
		}
		fclose($handle);
		
		//precalculate distances between cities
		$this->distances=[];
		for($i=0;$i<count($this->cities);$i++){
			for($j=0;$j<count($this->cities);$j++)
				if ($i!=$j)
					$this->distances[$i][$j]=$this->distance($i,$j);
		}
		for($i=0;$i<count($this->cities);$i++){
			asort($this->distances[$i]);
		}
		
		return $lines;
	}
	
	function readKnownSolutions(){
		$fname = $this->filename.".known";
		if (!file_exists($fname)) {
			file_put_contents($fname,"");
			return [];
		}
		$f = fopen($fname,"r");
		if (!$f) return [];	
		$known=[];
		while (($line = fgets($f)) !== false) {
			// process the line read
			$line = trim($line);
			if (!strlen($line)) continue;	//skip empty lines
			$parts = explode('=',$line);
			if (!isset($known[$parts[1]])) $known[$parts[1]] = $parts[0];
		}
		asort($known);
		fclose($f);
		return $known;
	}
	
	function markKnownSolution($solution){
		$fname = $this->filename.".known";
		$known = $this->readKnownSolutions();
		$score = $this->fitness($solution);
		$str = implode("-",$solution);
		if (!isset($known[$str])){
			file_put_contents($fname,sprintf("\n%.2f=%s",$score,$str),FILE_APPEND);
			return true;
		}else {
			return false;
		}			
	}

	function distance($i,$j){
		$a=$this->cities[$i];
		$b=$this->cities[$j];
		$d = sqrt(pow($a[0]-$b[0],2) + pow($a[1]-$b[1],2));
		return round($d,3);
	}
	
	//find nearest city, skipped those already visited
	function nearest($a,&$already=[]){
		$mind=-1;
		$minc=-1;
		$already[$a]=true;//already included a
		foreach($this->distances[$a] as $i=>$d){
			if (!isset($already[$i])) return $i;
		}
		/*
		for ($i=0;$i<count($this->cities);$i++){
			if (!isset($already[$i])){
				$d = $this->distance($a,$i);
				if ($mind<0 || $d<$mind){
					$minc = $i;
					$mind = $d;
				}
			}
		}
		return $minc;
		*/
	}
		
	function fitness($solution){
		$total=0;
		$last = count($solution)-1;
		for ($i=0;$i<$last;$i++){
			$a=$solution[$i];
			$b=$solution[$i+1];
			//$total+=$this->distance($a,$b);
			$total+=$this->distances[$a][$b];
		}
		//$total+=$this->distance($b,$solution[0]);
		$total+=$this->distances[$b][0];
		$total = round($total,3);
		return $total;
	}
	
	//simply go to next nearest city
	function lucky_solution(){
		//get it once, reuse later to avoid repetition
		if (empty($this->the_lucky_solution)){
			$path=[0];
			$already=[];
			while(count($path)<count($this->cities)){
				$path[]=$this->nearest(end($path),$already);
			}
			$this->the_lucky_solution = $path;
		}
		return $this->the_lucky_solution;
	}
	
	function sort_population($by='fitness'){
		
		$scores=[];
		$solutions = $this->population;//copy
		for($i=0;$i<count($solutions);$i++){
			$scores["$i"]=$this->fitness($solutions[$i]);
		}
		asort($scores);
		
		$total = array_sum($scores);
		$dist=[];
		foreach($scores as $i=>$fitness){
			$dist[$i] = round($fitness/$total,4);
		}
		asort($dist);
		
		$this->population=[];	
		$this->population_fitness=[];
		if ($by=='fitness'){
			foreach($scores as $i=>$score){
				$this->population[]=$solutions[(int)$i];
			}
		} else {
			foreach($dist as $i=>$val){
				$this->population[]=$solutions[(int)$i];
			}
		}
		for($i=0;$i<count($this->population);$i++){
			$this->population_fitness[$i] = $this->fitness($this->population[$i]);
		}
		
		return $this->population;
	}
	
	function init_population($population_size=50,$elite_size=5){
		$a = array_slice(array_keys($this->cities),1);
		$this->population=[];
		
		//always include the luckiest solution
		$lucky = $this->lucky_solution();
		$best = $this->fitness($lucky);
		
		$known=$this->readKnownSolutions();
		$known[implode('-',$lucky)] = $best;
		
		foreach($known as $solution=>$score){
			$this->population[]=explode('-',$solution);
		}
		$this->sort_population();
		//$this->population = array_slice($this->population,0,$elite_size);
		$left = $population_size - count($this->population);
		for($i=0;$i<$left;$i++){
			do {
				$b = $this->nearest_neighbor_solution();
				$bkey = implode('-',$b);
			} while (isset($known[$bkey]));	
			$this->population[]=$b;
			$known[$bkey]=1;
		}
		for ($i=$elite_size;$i<$population_size;$i++){
			do {
				$bkey = implode("-",$this->mutate($this->population[$i]));
			} while (isset($known[$bkey]));	
			$known[$bkey]=1;
		}
		/*
		for($i=0;$i<$left;$i++){
			do {
				//$b = $this->population[rand(0,$first)];
				//$this->mutate($b);
				$b = $this->nearest_neighbor_solution();
				$this->mutate($b);
				$bkey = implode('-',$b);
			} while (isset($known[$bkey]));	
			$this->population[]=$b;
			$known[$bkey]=1;
		}
		*/
		$this->population = array_slice($this->population,0,$population_size);
		return $this->sort_population();
	}	
	
	//do roulette wheel selection
	function select($matedPool,$matedPoolFitness,$algo='roulette'){
		$sum = array_sum($matedPoolFitness);
		$rand = lcg_value() * $sum;
		$s = 0;
		for($i=0;$i<count($matedPool);$i++){
			$s+=$matedPoolFitness[$i];
			if ($s>=$rand) {
				$selected=$this->population[$i];
				break;
			}
		}
		return $selected;
	}
	
	//do ordered crossover (add more randomization)
	function crossover($a,$b,$kcountmin=5){
		$all = array_values($a);
		$mid = ceil(count($a)/2);
		$kcount = rand(3,ceil(count($a)/5));
		$kfrom_a = rand(0,$mid-floor($kcount/2));
		$kto_a = $kfrom_a + $kcount-1;
		
		$kfrom_b = rand(0,$mid-floor($kcount/2));
		$kto_b = $kfrom_b + $kcount-1;
		
		// print_r("\nKeep $kcount nodes of a from $kfrom_a to $kto_a\n");
		// print_r("\nKeep $kcount nodes of b from $kfrom_b to $kto_b\n");
		
		$ka = array_slice($a,$kfrom_a,$kcount);
		$cb0 = array_merge(array_slice($b,0,$kfrom_a),array_slice($b,$kfrom_a+$kcount));		
		$cb=$cb0;
		
		$kb = array_slice($b,$kfrom_b,$kcount);		
		$ca0 = array_merge(array_slice($a,0,$kfrom_b),array_slice($a,$kfrom_b+$kcount));
		$ca=$ca0;
		
		// echo "\nKeep a = ".implode("-",$ka);
		// echo "\nCopy b = ".implode("-",$cb);	
		$already=[];
		for($i=0;$i<count($ka);$i++){
			$already[$ka[$i]]=true;
		} 
		for($i=0;$i<count($cb);$i++){
			if (in_array($cb[$i],$ka)){
				$cb[$i]=$ca0[$i];
			}
			if (isset($already[$cb[$i]])){
				for($j=0;$j<count($all);$j++)
					if (!isset($already[$all[$j]])) $cb[$i]=$all[$j];
			}
			$already[$cb[$i]]=true;
		}		
		$a1 = array_merge(array_slice($cb,0,$kfrom_a),$ka,array_slice($cb,$kfrom_a));
		//echo "\nCopy b = ".implode("-",$cb);
		for($i=0;$i<count($ca);$i++){
			if (in_array($ca[$i],$kb)){
				$ca[$i]=$cb0[$i];
			}
		}
		$already=[];
		for($i=0;$i<count($kb);$i++){
			$already[$kb[$i]]=true;
		} 
		for($i=0;$i<count($ca);$i++){
			if (in_array($ca[$i],$kb)){
				$ca[$i]=$cb0[$i];
			}
			if (isset($already[$ca[$i]])){
				for($j=0;$j<count($all);$j++)
					if (!isset($already[$all[$j]])) $ca[$i]=$all[$j];
			}
			$already[$ca[$i]]=true;
		}		
		$b1 = array_merge(array_slice($ca,0,$kfrom_b),$kb,array_slice($ca,$kfrom_b));
		// echo "\na = ".implode("-",$a);
		// echo "\nb = ".implode("-",$b);
		// echo "\na1 = ".implode("-",$a1);
		// echo "\nb1 = ".implode("-",$b1);
		return [$a1,$b1];
	}
	
	function mutate(&$a,$algo='rsm'){
		if ($algo=='swap'){
			$i = rand(1,count($a)-1);
			do {$j = rand(1,count($a)-1);} while ($j==$i);
			$tmp = $a[$i];
			$a[$i] = $a[$j];
			$a[$j] = $tmp;
		}elseif ($algo=='rsm'){
			$i = rand(1,count($a)-1);
			do {$j = rand(1,count($a)-1);} while ($j==$i);
			$start = min($i,$j);
			$end = max($i,$j);
			do{
				$tmp= $a[$start];
				$a[$start] = $a[$end];
				$a[$end] = $tmp;
				$start++;
				$end--;
			} while ($start < $end);
		}
		return $a;
	}
	
	function mutateMany(&$arr,$mutationRate=0.6,$mutationAlgo='rsm'){
		for($i=0;$i<count($arr);$i++) {
			if (lcg_value() < $mutationRate)
				$this->mutate($arr[$i],$mutationAlgo);
		}
		return $arr;
	}
	
	function printPopulation(){
		$a=[];
		for($i=0;$i<count($this->population);$i++){
			$a[]=sprintf("%.2f = %s",$this->population_fitness[$i],implode('-',$this->population[$i]));
		}
		echo "\n";
		print_r($a);
	}
	
	function printSolution($solution){
		echo sprintf("\n%.2f = %s",$this->fitness($solution),implode('-',$solution));
	}
	
	function nearest_neighbor_solution(){
		$countcities = count($this->cities);
		$unvisited=[];
		for($i=0;$i<$countcities;$i++){
			$unvisited[$i]=true;
		}
		$u = rand(0,$countcities-1);
		$unvisited[$u]=false;	
		$path=[$u];
		do{
			$mind=100000000;	//very large
			$minv=-1;
			foreach($unvisited as $v=>$bool){
				if ($unvisited[$v] && $this->distances[$u][$v]<$mind){
					$mind=$this->distances[$u][$v];
					$minv = $v;
				}
			}
			$path[]=$minv;
			$unvisited[$minv]=false;
			$u=$minv;
		} while (count($path)<$countcities);
		$path0=[];//start from first city
		$k=array_search(0,$path);
		$path0=array_merge(array_slice($path,$k),array_slice($path,0,$k));
		return $path0;
	}
	
	function iterate($population_size=50,$elite_size=5,$max_iterations=20000){
		$this->init_population($population_size,$elite_size);
		$this->best_solution = $this->population[0];
		$min = $this->population_fitness[0];
		$lucky = $this->fitness($this->lucky_solution());
		$knownsolutions = $this->readKnownSolutions();
		$stop = false;
		$iterations=0;
		$nochange=0;
		$this->printPopulation();
		//$combi = new Combination($elite_size,2);
		//$pairs = $combi->enum;
		$this->scores=[];
		do{
			$this->scores[]=$this->population_fitness[0];
			$iterations++;
			//keep best elements
			$retained = array_slice($this->population,0,$elite_size);
			//the rest to "mate" to produce children
			$matedPool = array_slice($this->population,$elite_size);
			$matedPoolFitness = array_slice($this->population_fitness,$elite_size);
			$children = [];
			for($i=0;$i<($population_size-$elite_size);$i++){
				//select 1st parent
				//$p1 = $this->select($matedPool,$matedPoolFitness);
				$p1 = $this->population[$i];
				//select 2nd parent, different from 1st one
				do{$p2 = $this->select($matedPool,$matedPoolFitness);}while($p2==$p1);
				$c = $this->crossover($p1,$p2);
				$children[]=$c[0];
				$children[]=$c[1];
			}
			//mutate children to add more entropy
			$this->mutateMany($children,1);
			$candidates = array_merge($retained,$children); 
			unset($retained);unset($children);unset($matedPool);unset($matedPoolFitness);
			$known=[];
			$this->population=[];
			for($i=0;$i<count($candidates);$i++){
				$str = implode('-',$candidates[$i]);
				if (!isset($known[$str])){
					$this->population[]=$candidates[$i];
					$known[$str]=true;
				}
			}
			$this->sort_population();
			//truncate redundant elements to keep population size unchanged
			$this->population = array_slice($this->population,0,$population_size);
			$this->population_fitness = array_slice($this->population_fitness,0,$population_size);
			$this->scores[]=$this->population_fitness[0];
			
			//remember solution if better than the lucky solution
			if ($this->population_fitness[0]<$lucky){
				$str = implode('-',$this->population[0]);
				if (!isset($knownsolutions[$str])){
					//echo sprintf("\n\t log known solution: %.2f=%s",$this->population_fitness[0],$str); 
					$knownsolutions[$str]=$this->population_fitness[0];
					file_put_contents($this->filename.".known",
						sprintf("\n%.2f=%s",$this->population_fitness[0],$str),
						FILE_APPEND | LOCK_EX
					);
				}
			}
			
			//update best score
			if ($this->population_fitness[0]<$min){
				$this->best_solution=$this->population[0];
				$nochange=0;
				echo "\nIteration #$iterations";
				echo sprintf("\n%.2f = %s",$this->population_fitness[0],implode("-",$this->population[0]));
			} else {
				$nochange++;
			}
			//no change, re-randomize to try
			if ($nochange>0 && 0 == ($nochange % 500)){
				echo "\n\t rerandomize ...";
				$this->init_population($population_size,$elite_size);
				//$nochange=0;
			}
			$stop = $iterations>=$max_iterations || $nochange>0.25*$max_iterations;
		} while (!$stop);
		$best_fitness = $this->fitness($this->best_solution);
		$best_fn = $this->filename.".best";
		if (file_exists($best_fn)){
			$json = explode('=',file_get_contents($best_fn));
		} else{
			$json = [1000000000,''];
		}
		$bingo=false;
		if (round($best_fitness,2) < round($json[0],2)){
			$bingo=true;
			echo "\nBINGO! WE FOUND A BETTER SOLUTION!";
			$json[0]=$best_fitness;
			$json[1]=implode("-",$this->best_solution);
			file_put_contents($best_fn,sprintf("%.2f=%s",$json[0],$json[1]));
		}
		return array(
			"total"=>$best_fitness,
			"path"=>implode('-',$this->best_solution),
			"bingo"=>$bingo,
		);	
	}
}

class Combination{
	var $x=[];
	var $enum=[];
	
	function __construct($n,$k=2){
		$this->listCombi($n,$k);
	}
	
	function listCombi($n,$k=2){
		for($i=0;$i<=$n;$i++) $this->x[$i]=$i;
		$this->recur(1,$k,$n);
		return $this->enum;
	}
	
	private function addCombi($a, $n){
		$c=[];
		for ($i = 1; $i <= $n; $i++){
			$c[]=$a[$i];
		}
		$this->enum[]=$c;
		//$this->enum[]=implode(",",$c);
	}
	
	private function recur($h,$k,$n){
		for ($i = $this->x[$h-1] + 1; $i <= $n - ($k-$h); $i++){
			$this->x[$h] = $i;
			if ($h == $k){
				$this->addCombi($this->x, $k);
			} else {
				$this->recur($h+1, $k, $n);
			}
		}
	}
}
	
