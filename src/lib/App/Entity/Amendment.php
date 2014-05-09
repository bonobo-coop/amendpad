<?php

namespace App\Entity;

use App\Entity\Base;
use App\Entity\Vote;

class Amendment extends Base 
{
    const STATUS_PENDING  = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    
    /** @var string **/
    public $tid;
    /** @var string **/
    public $body;
    /** @var string **/
    public $reason;
    /** @var string **/
    public $uid;
    /** @var int **/
    public $status;
    /** @var boolean **/
    public $addition;
    /** @var array **/
    public $votes;
    /** @var array **/
    public $votesCounter;
        
    /**
     * Add vote to amendment (embedded docs)
     * @param Vote $vote
     * @return Amendment
     */
    public function addVote(Vote $vote)
    {
        $this->votes[$vote->ip] = $vote->option;
        $this->updated = time();
        
        $this->updateVotesCounter();
        
        return $this;
    }
    
    /**
     * Get amendment vote (embedded docs)
     * @param string $ip
     * @return Amendment
     */
    public function getVote($ip)
    {
        return isset($this->votes[$ip]) ? $this->votes[$ip] : null;
    }
    
    /**
     * Refresh votes counter
     * @return Amendment
     */
    public function updateVotesCounter()
    {
        $this->votesCounter = array(
            Vote::OPTION_LIKE => 0,
            Vote::OPTION_DISLIKE => 0,
            Vote::OPTION_DOUBT => 0,
        );
        
        foreach ($this->votes as $option) {
            $this->votesCounter[$option]++;
        }
        
        return $this;
    }
}