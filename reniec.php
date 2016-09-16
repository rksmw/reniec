<?php
	//ini_set('max_execution_time', 3600); //3600 segundos = 60 minutos
	//ini_set("memory_limit", "5000M"); //5GB +-
	class MyDB extends SQLite3 // PHP 5.3+
	{
		function __construct()
		{
			$this->open('solver.db');
		}
	}
	class Reniec
	{
		var $cc;
		var $db;
		function __construct()
		{
			date_default_timezone_set('America/Lima');
			if(!session_id())
			{
				session_start();
				session_cache_limiter('private');
				session_cache_expire(2); // 2 min.
			}
			$this->cc = new cURL(true,'https://cel.reniec.gob.pe/valreg/valreg.do',dirname(__FILE__).'/cookies.txt');

			$this->db = new MyDB();
			/*
			if(!$db){
				echo $db->lastErrorMsg();
			}
			else{
				echo "success\n";
			}*/
		}

		function GetSession($indice)
		{
			if(isset($_SESSION[$indice]))
			{
				return $_SESSION[$indice];
			}
			return false;
		}

		function PutSession($indice, $valor)
		{
			$_SESSION[$indice] = $valor;
			return true;
		}

		function CodValidacion($dni) // no test
		{
			$hashNumbers = array('6', '7', '8', '9', '0', '1', '1', '2', '3', '4', '5');
			$hashLetters = array('K', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
			if ($dni!="" || strlen($dni) == 8)
			{
				$suma = 0;
				$hash = array(5, 4, 3, 2, 7, 6, 5, 4, 3, 2);

				$digit = strlen($dni);

				$diff = count($hash) - $digit;

				for ($i = $digit - 1; $i >= 0; $i--)
				{
					$suma += ($dni[$i] - 0) * $hash[$i + $diff];
				}

				$suma = 11 - ($suma % 11);

				if ($suma == 11)
				{
					$suma = 0;
				}
				else if ($suma == 10)
				{
					$suma = 1;
				}
				return $hashNumbers[$suma];
			}
			return "";
		}

		function DescargaCaptcha($name)
		{
			//return true;
			$data = array();
			$ref="https://cel.reniec.gob.pe/valreg/valreg.do";
			$url="https://cel.reniec.gob.pe/valreg/codigo.do";
			$this->cc->referer($ref);
			$captcha = $this->cc->get($url,$data);
			if($captcha!=false)
			{
				file_put_contents($name, $captcha);
				return true;
			}
			return false;
		}

		function ProcesaCaptha($name)
		{
			$captcha = $this->GetSession("captcha");
			$stime = $this->GetSession("stime");
			if( $captcha!=false && $stime+(2*60) > time() )
			{
				return $captcha;
			}

			$name = dirname(__FILE__)."/".$name;
			if($this->DescargaCaptcha($name))
			{
				$image = @imagecreatefromjpeg($name);
				if($image)
				{
					imagefilter($image, IMG_FILTER_GRAYSCALE);
					imagefilter($image, IMG_FILTER_BRIGHTNESS,100);
					imagefilter($image, IMG_FILTER_NEGATE);
					$L1 = imagecreatetruecolor(25, 20);
					$L2 = imagecreatetruecolor(25, 20);
					$L3 = imagecreatetruecolor(25, 20);
					$L4 = imagecreatetruecolor(25, 20);

					imagecopyresampled($L1, $image, 0, 0, 13, 10, 25, 20, 25, 20);
					imagecopyresampled($L2, $image, 0, 0, 43, 15, 25, 20, 25, 20);
					imagecopyresampled($L3, $image, 0, 0, 76, 10, 25, 20, 25, 20);
					imagecopyresampled($L4, $image, 0, 0, 106,15, 25, 20, 25, 20);

					$query = "SELECT (SELECT Caracter FROM Diccionario WHERE Codigo1='".$this->ConvirteTexto($L1)."') AS c1,(SELECT Caracter FROM Diccionario WHERE Codigo2='".$this->ConvirteTexto($L2)."') AS c2,(SELECT Caracter FROM Diccionario WHERE Codigo3='".$this->ConvirteTexto($L3)."') AS c3,(SELECT Caracter FROM Diccionario WHERE Codigo4='".$this->ConvirteTexto($L4)."') AS c4";

					$rpt = $this->db->query($query);
					if( $row = $rpt->fetchArray(SQLITE3_ASSOC) )
					{
						$this->PutSession("captcha", $row["c1"].$row["c2"].$row["c3"].$row["c4"]);
						$this->PutSession("stime", time());
						return $row["c1"].$row["c2"].$row["c3"].$row["c4"];
					}
				}
			}
			return false;
		}
		function ConvirteTexto($image)
		{
			$rtn="";
			$w = imagesx($image);
			$h = imagesy($image);
			for($y=0; $y<$h;$y++)
			{
				for($x=0; $x<$w;$x++)
				{
					$rgb = imagecolorat($image, $x, $y);
					$r = ($rgb >> 16) & 0xFF;
					$g = ($rgb >> 8) & 0xFF;
					$b = $rgb & 0xFF;
					if((($r+$g+$b)/255) < 1)
					{
						$rtn.="0";
					}
					else
					{
						$rtn.="1";
					}
				}
			}
			return $rtn;
		}

		// BUSQUEDA DE DATOS EN RENIEC (Solo Nombres, Apellidos y Cod.Verificacion)
		function BuscaDatosReniec($dni)
		{
			$rtn=array();
			$Captcha = $this->ProcesaCaptha("captcha.jpg");
			if( $dni!="" && $Captcha != false )
			{
				$data = array(
					"accion" => "buscar",
					"nuDni" => $dni,
					"imagen" => $Captcha
				);
				$url = "https://cel.reniec.gob.pe/valreg/valreg.do";
				$this->cc->referer($url);
				$Page = $this->cc->post($url,$data);
				$Page = utf8_encode($Page);
				$posiN = strpos($Page,'<td height="63" class="style2" align="center">');
				$Page = substr($Page,$posiN+48,254);
				$posfN = strpos($Page,'<br>');
				$Nombre = substr($Page,0,$posfN);
				$Separado = explode("\r\n",$Nombre);
				if(isset($Separado) && count($Separado)==3)
				{
					$Nombre = trim($Separado[1])." ".trim($Separado[2]).", ".trim($Separado[0]);
				}
				else
				{
					$Nombre = preg_replace("[\s+]"," ", ($Nombre));
					$Nombre = trim($Nombre);
				}
				$patron='/<font color=#ff0000>([A-Z0-9]+) <\/font>/';
				$output = preg_match_all($patron, $Page, $matches, PREG_SET_ORDER);
				if(isset($matches[0]))
				{
					$rtn = array("DNI"=>$dni,"Nombre"=>$Nombre,"CodVerificacion"=>trim($matches[0][1]));
				}
				if(count($rtn)>0)
				{
					return $rtn;
				}
			}
			return false;
		}
	}
?>
