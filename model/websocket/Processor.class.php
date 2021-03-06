<?php
class Processor
{
	private $socket;
	private $app;
	function __construct(&$socket)
	{
		$this->app = Application::getInstance();
		$this->socket=$socket;
	}
	
	public function processesMessage(&$user,&$msg)
	{
	  
	   try
	    {
	     $test = $this->app->db->prepare("select 1");
	     $test->execute();
	    }catch(Exception $e)
	       {
		 $this->app->db = new PDO(db_ConnectionString,db_user,db_pass);
		 $this->app->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		 }
	  
		if($msg ==null)
  			return;
  		
  		$obj= json_decode($msg);
  		
  		if($obj == null)
  			return;
  		
  		switch($obj->type)
  		{
  			case 'SYSregBoard':
  				if(is_numeric($obj->roomId))
  				{
  					$user->doodleBoardId = $obj->roomId;
  					$this->socket->say ("REGISTER ".$user->id." on ".$obj->roomId);
  					$this->updateNumber($obj->roomId);
  				}
  			break;
  			case 'SYSreplayBoard':
  				$user->replayId=$obj->roomId;
  				$this->socket->send($user->socket,$this->prepareReplay($obj->roomId));
  			break;
  			case 'nextScribble':
  				$this->socket->send($user->socket,$this->returnNextScribble($user->replayId, $obj->num));

  			break;
  			default:
				try{
		   		$this->socket->say("RECIEVE< ".$msg);
		   		$this->socket->say("POSTING to ".$user->doodleBoardId);
					$s = $this->app->db->prepare("insert into Scribble (DoodleBoardId," .
							"xCoords," .
							"yCoords," .
							"type," .
							"color," .
							"metaData," .
							"width," .
							"timeCreated,serverTimeCreated) values (:id,:xc,:yc,:ty,:c,:m,:w,:ti,:rc)");
							
					$s->bindParam(':id',$user->doodleBoardId);
					$s->bindParam(':xc',implode(":",$obj->xCoords));
					$s->bindParam(':yc',implode(":",$obj->yCoords));
					$s->bindParam(':ty',$obj->type);
					$s->bindParam(':rc',time());
			
					$newColor = hexdec($obj->color);
					
					$s->bindParam(':c',$newColor);
					$s->bindParam(':m',$obj->metaData);
					$s->bindParam(':w',$obj->width);
					$s->bindParam(':ti',$obj->timeCreated);
					$s->execute();		   		}
				catch(Exception $e)
				{var_dump($e);}
			    foreach($this->socket->users as $client)				
				{
					if($client->doodleBoardId == $user->doodleBoardId)
					{
						$this->socket->say ("	posting to ".$client->id);
						if($client != $user)		
							$this->socket->send($client->socket,$msg);
					}

				} 			
  		}		
	}
	public function updateNumber($boardId)
  	{
	   try
	    {
	     $test = $this->app->db->prepare("select 1");
	     $test->execute();
	    }catch(Exception $e)
	       {
		 $this->app->db = new PDO(db_ConnectionString,db_user,db_pass);
		 $this->app->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		 }  	
  	
  		$num = 0;
		foreach($this->socket->users as $client)				
			if($client->doodleBoardId == $boardId)
				$num++;
		$out = '{"type":"SYSusers","num":'.$num.'}';
		
		
		$s = $this->app->db->prepare("delete from BoardUsers where boardId=:id");
		$s->bindParam(':id',$boardId);
		$s->execute();
		if($num > 0)
		{
			$s = $this->app->db->prepare("insert into BoardUsers set users=:num, boardId=:id");
			$s->bindParam(':num',$num);	
			$s->bindParam(':id',$boardId);
			$s->execute();
		}
		
		foreach($this->socket->users as $client)				
			if($client->doodleBoardId == $boardId)		
				$this->socket->send($client->socket,$out);			
			  		
  	}
  	public function prepareReplay($boardId)
  	{
  		if(!is_numeric($boardId))
  			return;
  		$db = &$this->app->db;
  		
  		$s = $db->prepare("select count(id) as num from Scribble where DoodleBoardId=:id");
  		$s->bindParam(':id',$boardId);
  		$s->execute();
  		$obj = $s->fetchObject();
  
  		$ret = array("type"=>"SYSinit","numScribs"=>$obj->num);
  		return json_encode($ret);	
  	}
  	public function returnNextScribble($boardId,$num)
  	{
  		$this->socket->say($num);
   		if(!is_numeric($boardId) || !is_numeric($num))
  			return;
  		$db = &$this->app->db; 		
  		$s=$db->prepare("SELECT xCoords,yCoords,type,color,metaData,width,timeCreated,serverTimeCreated FROM `Scribble` WHERE DoodleBoardId = :bid order by serverTimeCreated asc, timeCreated asc limit ".$num.",1");
  		$s->bindParam(':bid',$boardId);  		
  		$s->execute();
  		$ret= $s->fetchObject();
  		
  		if($ret)
  		{
  			$ret->xCoords = explode(":",$ret->xCoords);  		
  			$ret->yCoords = explode(":",$ret->yCoords);
  			$ret->color = substr(dechex($ret->color + hexdec('FF000000')),2);
  			return json_encode($ret);
  		}else
  			return json_encode(array("type"=>"SYSendBoard"));
  	}
}
?>
