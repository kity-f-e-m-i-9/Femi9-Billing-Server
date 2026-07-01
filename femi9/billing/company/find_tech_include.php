<!-----	
<script type="text/javascript">
function showstate(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintstate").innerHTML=xmlhttp.responseText;}}
var name="10";
xmlhttp.open("GET","loadData.php?q="+str + '&name='+ name,true);
xmlhttp.send();}
</script>

<script type="text/javascript">
function showpincode(talukid,str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintpincode").innerHTML=xmlhttp.responseText;}}
var name="10";
xmlhttp.open("GET","loadPincode.php?q="+str + '&talukid='+ talukid,true);
xmlhttp.send();}
</script>---->

<!-----<script type="text/javascript">
function showtaluk(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHinttaluk").innerHTML=xmlhttp.responseText;}}
var name="10";
xmlhttp.open("GET","loadtaluk.php?q="+str + '&name='+ name,true);
xmlhttp.send();}
</script>---->
