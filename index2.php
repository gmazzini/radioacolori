<html>

<script>
function loadDoc(params) {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      document.getElementById("myout").innerHTML = this.responseText;
    }
  };
  xhttp.open("GET","pippo.php?myid="+params,true);
  xhttp.send();
}

var mysec = 0;
var x = setInterval(function() {
  var xhttp = new XMLHttpRequest();
  var xhttp2 = new XMLHttpRequest();
  var now = new Date().getTime();
  var mytime = Math.floor(now/1000);
  document.getElementById("myreload").innerHTML = mysec-mytime;
  if (mytime > mysec) {
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        var n = this.responseText.search("Fine:");
        mysec = parseInt(this.responseText.substring(n+6, n+17),10) + 30;
        document.getElementById("mylisten").innerHTML = this.responseText.substring(0, n-1);
      };
    };
    xhttp.open("GET", "mylisten.php", true);
    xhttp.send();
    xhttp2.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        document.getElementById("myagenda").innerHTML = this.responseText;
      };
    };
xhttp2.open("GET", "myagenda.php", true);
    xhttp2.send();
  };
}, 1000);

</script>

<img src="logo.png" width="20%" height="auto">

<pre>
Prossimo brano tra: <div style="display:inline" id="myreload"></div>
I Colori del Navile presentano Radio a Colori
Musica libera con licenza CC-BY
</pre>

<table>

<tr><pre>
<font color="blue">Ricerca</font>
<input id="myid"><button type="button" onclick="loadDoc(document.getElementById('myid').value)">Search</button>
<div id="myout"></div>
</pre></tr>

<tr><pre>
<font color="blue">State Ascoltando</font>
<div id="mylisten"></div>
</pre></tr>

<tr><pre>
<font color="blue">Palinsesto</font>
<div id="myagenda"></div>
</pre></tr>

</table>
<pre>
Powered by I Colori del Navile
Email info at radioacolori.net
CF 91357680379
ROC 33355
</pre>

</html>
