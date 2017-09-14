<?php

namespace FlSouto;

class DrumMachine{

	protected $len = .2;
	protected $samples = [];
	protected $freqs = [];
	protected $pattern = '?';
	protected $ride = null;
	protected $ride_ratios = null;

	function discover($dir, $query){
		if(is_array($query)){
			$query = $query[array_rand($query)];
		}
		if(!strstr($query,'.') && !strstr($query,'*')){
			$query = '*'.$query.'*.wav';
		}
		if(substr($query,0,1)!='/'){
			$query = realpath(__DIR__.'/../'.$dir)."/".$query;
		}
		$files = glob($query);
		shuffle($files);
		return current($files);
	}

	function len($len){
		$this->len = $len;
		return $this;
	}

	function snare($query, $freq=1){
		$this->samples['s'] = $this->discover('snares',$query);
		$this->freqs['s'] = $freq;
		return $this;
	}

	function kick($query, $freq=1){
		$this->samples['k'] = $this->discover('kicks',$query);
		$this->freqs['k'] = $freq;
		return $this;
	}

	function void($freq=1){
		$this->samples['_'] = Sampler::silence($this->len);
		$this->freqs['_'] = $freq;
		return $this;
	}

	function ride($query, $ratios=[1,2,4,8]){
		$this-> ride = $this->discover('rides',$query);
		if(!is_array($ratios)){
			$ratios = [$ratios];
		}
		$this->ride_ratios = $ratios;
		return $this;
	}

	function add($id, $sample, $freq=1){
		$this->samples[$id] = $sample;
		$this->freqs[$id] = $freq;
		return $this;
	}

	function mkseq($size, $pattern='?'){

		$samples = [];
		
		foreach($this->freqs as $k => $val){
			for($i=1;$i<=$val;$i++){
				$samples[] = $k;
			}
		}
		if(!is_array($pattern)){
			$pattern = str_split($pattern);
		}

		$queue = $pattern;
		$sequence = [];

		for($i=1;$i<=$size;$i++){

			if(empty($queue)){
				$queue = $pattern;
			}

			$k = array_shift($queue);

			if($k=='?'){
				$k = $samples[array_rand($samples)];
			}

			$sequence[] = $k;

		}

		return implode($sequence);

	}

	function compile(){
		$samples = [];
		foreach($this->samples as $id => $sample){
			if($id=='_'){
				$samples['_'] = Sampler::silence($this->len);
				continue;
			}
			if(is_string($sample)){
				$samples[$id] = new Sampler($sample);
				$samples[$id]->cut(0,$this->len);
			} else {
				$samples[$id] = $sample;
			}
		}
		return $samples;
	}

	function render($sequence, $callback = false){
		$samples = $this->compile();
		$stream = null;
		$i = 1;
		foreach(str_split($sequence) as $k){
			$smp = $samples[$k];
			if($callback){
				if($return = $callback($smp, $k, $i)){
					$smp = $return;
				}
			}
			if($stream){
				$stream->add($smp);
			} else {
				$stream = $smp();
			}
			$i++;
		}
		if($this->ride){
			$ride = new Sampler($this->ride);
			$ratio = $this->ride_ratios[array_rand($this->ride_ratios)];
			$ride->cut(0,$this->len * $ratio)->x(round(strlen($sequence)/$ratio))->cut(0,$stream->len());
			$stream->mix($ride, false);
		}
		return $stream;
	}

}