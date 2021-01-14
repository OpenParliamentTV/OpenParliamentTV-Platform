<?php


/**
 * @param $mail
 * Returns the User Object if found.
 */
function getUserdata($mail) {

	if (!$mail) {
		return;
	} else {
		$users = json_decode(file_get_contents(__DIR__."/userdata.json"),true);
		foreach ($users as $k=>$user) {
			if ($user["mail"] == $mail) {
				$ret["success"] = "true";
				$ret["index"] = $k;
				$ret["user"] = $user;
				return $ret;
			}
		}
	}

}



/**
 * sharedFile class
 * @class           sharedFile
 * @file            shared/sharedFile.class.php
 * @brief           This class use read/write file with a lock state
 * @version         0.1
 * @date            2012-06-28
 * @copyright       OpenSource : LGPLv3
 *
 * This class use read/write file with a lock state
 */
class sharedFile{
	private $file;
	private $filename;
	private $fileExist;
	private $locked;

	/**
	 * Constructor
	 * @param string $file The file to read
	 */
	public function __construct($file){
		$this->locked = false;
		$this->filename = $file;
		$this->fileExist = file_exists($file);

		//Trying to create file
		if($this->fileExist === false){
			touch($file);
		}

		$this->file = @fopen($file, "rb+");
		if($this->file !== false){
			$this->locked = flock($this->file, LOCK_EX);
		}
	}

	/**
	 * Destructor : Perform a lock release if needed
	 */
	public function __destruct(){
		$this->close();
	}

	/**
	 * Get the exist state of the file
	 * @return boolean True if the file exist, false else
	 */
	public function exists(){
		return $this->fileExist;
	}

	/**
	 * Get the filename of the current watched file
	 * @return string The filename
	 */
	public function getFilename(){
		return $this->filename;
	}

	/**
	 * Get the file content (alias of read function)
	 * @return mixed False if there is an open or locked error, a string if the content was fully readed
	 */
	public function get(){
		return $this->read();
	}

	/**
	 * Get the file content
	 * @return mixed False if there is an open or locked error, a string if the content was fully readed
	 */
	public function read(){
		if($this->file === false && $this->locked !== true){
			return false;
		}

		//Start from beginning
		fseek($this->file, 0);
		$result = "";
		//Read data
		while(!feof($this->file)){
			$result .= fgets($this->file, 4096);
		}
		return $result;
	}

	/**
	 * Set the file content (alias of write function)
	 * @param string $data The data to store into file
	 * @return boolean True if the data where saved, false else.
	 */
	public function set($data){
		return $this->write($data);
	}

	/**
	 * Set the file content
	 * @param string $data The data to store into file
	 * @return boolean True if the data where saved, false else.
	 */
	public function write($data){
		if($this->file === false  && $this->locked !== true){
			return false;
		}

		//Clearing content and go back to first characters
		ftruncate($this->file, 0);
		rewind($this->file);

		//Save data (in UTF8 if possible)
		if(!mb_detect_encoding($data, "UTF-8", true)){
			fwrite($this->file, utf8_encode($data));
		}else{
			fwrite($this->file, $data);
		}
		fflush($this->file);
		return true;
	}

	/**
	 * Write data to file, and close, send back the write state result
	 * @param string $data The data to store into file
	 * @return boolean True if the data where saved, false else.
	 */
	public function writeClose($data){
		$tmp = $this->write($data);
		$this->close();
		return $tmp;
	}

	/**
	 * Get back the lock state of the file
	 * @return boolean True the file is locked, false if the lock failed
	 */
	public function isLocked(){
		return $this->locked;
	}

	/**
	 * Close the openned file and remove lock
	 */
	public function close(){
		if($this->locked === true){
			flock($this->file, LOCK_UN);
		}
		$this->locked = false;

		if(($this->file !== false) && (get_resource_type($this->file) === "stream")){
			fclose($this->file);
		}
	}
}




?>