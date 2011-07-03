<?php

class Antispam 
{
	protected $corpus;
	private $neutral = 0.5;
	
	public function __construct(Corpus $corpus) {
		$this->corpus = $corpus;
	}
	
	/**
	 * Calculate lexeme value with Paul Graham method. 
	 * To prevent over-interpreting the messages as spam, 
	 * the number of instances of innocent lexemes is 
	 * multiplied by 2.
	 * @link http://www.paulgraham.com/spam.html
	 * 
	 * @param int $wordSpamCount
	 * @param int $wordNoSpamCount
	 * @param int $spamMessagesCount
	 * @param int $noSpamMessagesCount
	 * 
	 * @return float
	 */
	public static function graham($wordSpamCount, $wordNoSpamCount, $spamMessagesCount, $noSpamMessagesCount) 
	{
		$value = ($wordSpamCount / $spamMessagesCount) / (($wordSpamCount/$spamMessagesCount) + ((2 * $wordNoSpamCount) / $noSpamMessagesCount));
		
		return $value;
	}
	
	/**
	 * Calculate lexeme value with Gary Robinson method
	 * 
	 * @param int $wordOccurrences Number of occurrences in corpus (in spam and nospam)
	 * @param float $graham Word value calculated by Graham method
	 * 
	 * @return float
	 */
	public function robinson($wordOccurrences, $wordGrahamValue) 
	{
		$s = 1;
		$x = 0.5;
		
		$value = ($s * $x + $wordOccurrences * $wordGrahamValue) / ($s + $wordOccurrences);
		
		return $value;
	}
	
	/**
	 * Calculate bayes probability
	 * 
	 * @param array $lexemes
	 */
	public function bayes(array $lexemes)
	{
		$numerator = 1;
		$denominator = 1;
		foreach($lexemes as $lexeme) {
			$numerator *= $lexeme['probability'];
			$denominator *= 1 - $lexeme['probability'];
		}
		
		$result = $numerator / ($numerator + $denominator);
		
		return $result;
	}
	
	public function isSpam($text)
	{	
		foreach($this->corpus->lexemes as $word => $value) {
			$graham = $this->graham(
				$value['spam'], 
				$value['nospam'], 
				$this->corpus->messagesCount['spam'], 
				$this->corpus->messagesCount['nospam']
			);
			
			$probability = $this->robinson($value['spam'] + $value['nospam'], $graham);
			$this->corpus->lexemes[$word]['probability'] = $probability;
		}
		
		// create decision matrix
		$usefulnessArray = array();
		$decisionMatrix = array();
		
		$words = explode(' ', $text);
		
		foreach($words as $word) {
			$word = trim($word);
			if(strlen($word) > 0) {
				// first occurence of lexeme (unit lexeme)
				if(!isset($this->corpus->lexemes[$word])) {
					// set default / neutral lexeme probability
					$probability = $this->neutral;
				} else {
					$probability = $this->corpus->lexemes[$word]['probability'];
				}
				
				$usefulness = abs($this->neutral - $probability); // distance from neutral value
				$decisionMatrix[$word]['probability'] = $probability;
				$decisionMatrix[$word]['usefulness'] = $usefulness;
				$usefulnessArray[] = $usefulness;
			}
		}
		
		// sort by usefulness
		array_multisort($usefulnessArray, SORT_DESC, $decisionMatrix);
		
		// need only first 15
		$mostImportantLexemes = array_slice($decisionMatrix, 0, 15);
		
		$result = $this->bayes($mostImportantLexemes);
		
		return $result;
	}
}

?>
