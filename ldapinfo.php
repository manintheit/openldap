<?php
/*
$array = array(
  "cn=vault_admin,ou=vault,ou=Groups,dc=homelab,dc=io" => array(
       "cn" => "vault_admin",
       "member" => array(
           "cn=user001,ou=People,dc=homelab,dc=io",
           "cn=user002,ou=People,dc=homelab,dc=io"
           
           )
  )
);
*/
header_remove("X-Powered-By");
header('Content-Type: application/json');
// LDAP URI
$ldapuri = "ldap://10.213.160.187:389"; 
// Connecting to LDAP
// This does not connect to ldap to check connection only checks the uri.
$ldapconn = ldap_connect($ldapuri)
          or die("That LDAP-URI was not parseable");
$ds = ldap_connect("$ldapuri");
$filter = "(&(objectClass=groupofnames))";
// single filter
$attributes = array('dn', 'cn', 'member');
$member_arr = array();
$ldap_arr = array();
if ($ds){
  $sr = ldap_search($ds,"dc=homelab,dc=io",$filter,$attributes); 
  $info = ldap_get_entries($ds, $sr);

  for ($i = 0 ; $i < $info['count']; $i ++){
    $dn = $info[$i]['dn'];
    $cn = $info[$i]['cn'][$i];
    for ($j = 0; $j < $info[$i]['member']['count']; $j ++ ){
      //echo $info[$i]['member'][$j] . "<br/>";
      array_push($member_arr, $info[$i]['member'][$j]);
    }
  $arr = array(
    $dn => array(
         "cn" => $cn,
         "member" => array_values($member_arr)    
      )
  );
  array_push($ldap_arr, $arr);
  //clear array
  unset($member_arr);
  $member_arr = array();
  }
//echo json_encode($ldap_arr, JSON_FORCE_OBJECT);

//Prints Json with removing []

foreach ($ldap_arr as $attribute) {
  $entity[key($attribute)] = reset($attribute);
}
echo json_encode($entity);

} else {
  echo "<h1> LDAP Connection ERRORR!</h1>";
}
ldap_close($ldapconn);         
?>
