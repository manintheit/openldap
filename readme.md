## OpenLDAP Server Installation, Configuration and Hardening.

This is one of the longest post that covers installation, configuration and hardening of OpenLDAP server. I had limited knowledge of LDAP directories and management of LDAP servers. Other than that, there is little information on the Internet that I used it for the real world examples.

I will not delineate the LDAP directories, instead I will focus on installation, configuration and hardening part.


**You can access all ldif files for this post in my [github](https://github.com/manintheit/openldap)**

###### Installation OpenLDAP on Ubuntu 18.04
```shell
root@ldap:~# sudo apt install slapd ldap-utils
```

###### Initial configuration of OpenLDAP

You can reconfigure *slapd* with command below. But this is just a initial configuration. For further customization such as disabling anonymous bind, securing LDAP and hardening part we need work a bit more.

```shell
root@ldap:~# dpkg-reconfigure slapd
```

#### Creating CA and LDAP server Certificate.
I will also show how to create a Root Certificate to sign OpenLDAP csr that we are going to create very soon in this post.

###### Generate Root Certificate Key
This certificate will be used to sign the certificate for our OpenLDAP server. So, if you also use this certificate to sign other certificates, it is very important to keep it safe place that only privileged users can access it.

```shell
root@ldap:~# openssl genrsa -des3 -out myCA.key 4096
```
###### Generate Root Certificate

```shell
root@ldap:~# openssl req -x509 -new -nodes -key myCA.key -sha256 -days 3650 -out myCA.pem
```

###### Generate Key for OpenLDAP Certificate

```shell
root@ldap:~# openssl genrsa -out ldap.homelab.io.key 4096
```

###### Generate CSR to create Signed OpenLDAP Certificate
```shell
root@ldap:~# openssl req -new -sha256 -key ldap.homelab.io.key -subj "/C=DE/ST=Hessen/O=MyOrg, Inc./CN=ldap.homelab.io" -out ldap.homelab.io.csr
```

You can verify your csr with the following command. By the way, created csr will be used to create a certificate for OpenLDAP server signing it our Root Certificate(CA). So, it is self-signed certificate.

```shell
root@ldap:~# openssl req -in ldap.homelab.io.csr -noout -text
```

###### Create Self-Signed Certificate for OpenLDAP Server

```shell
root@ldap:~# openssl x509 -req -in ldap.homelab.io.csr -CA myCA.pem -CAkey myCA.key -CAcreateserial -out ldap.homelab.io.crt -days 730 -sha256
```

You can also verif the SSL certificate.

```shell
root@ldap:~# openssl x509 -in ldap.homelab.io.crt -text -noout
```

We have following files for OpenLDAP certificates.

```shell
root@ldap:/etc/ldap/certs# ls
 ldap.homelab.io.pem ldap.homelab.io.key CA.pem
```

* 16.1.1. Server Certificates
The DN of a server certificate must use the CN attribute to name the server, and the CN must carry the server's fully qualified domain name. Additional alias names and wildcards may be present in the subjectAltName certificate extension. More details on server certificate names are in RFC4513.

* 16.1.2. Client Certificates
The DN of a client certificate can be used directly as an authentication DN. Since X.509 is a part of the X.500 standard and LDAP is also based on X.500, both use the same DN formats and generally the DN in a user's X.509 certificate should be identical to the DN of their LDAP entry. However, sometimes the DNs may not be exactly the same, and so the mapping facility described in Mapping Authentication Identities can be applied to these DNs as well.

[Reference: OpenLDAP](https://www.openldap.org/doc/admin24/tls.html#:~:text=Server%20Certificates,certificate%20names%20are%20in%20RFC4513.)


###### Adding CA Certificate to truststore on OpenLDAP server and Client machines.
As we are using self-signed certificate, we need to add CA to truststore of the clients machines. Otherwise, you will get an error on client machines while connecting to OpenLDAP server.


###### Adding truststore on Ubuntu
Adding truststore may vary some GNU/Linux distributions. So, you can find the procedure for the Ubuntu.

```shell
root@ldap:~# cp myCA.crt /usr/share/ca-certificates/

root@ldap:~# dpkg-reconfigure ca-certificates
Updating certificates in /etc/ssl/certs...
0 added, 1 removed; done.
Processing triggers for ca-certificates (20201027ubuntu0.18.04.1) ...
Updating certificates in /etc/ssl/certs...
```


###### Cloning LDIF files from github Repo
OpenLDAP 2.3 and later have transitioned to using a dynamic runtime configuration engine. So, slapd.conf will be deprecated. Because of that, we configure every configuration with the new approach instead of **slapd.conf**

```shell
root@ldap:~# git clone https://github.com/manintheit/openldap
```
###### Configuring SSL for OpenLDAP
```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 00-tls.ldif
```

**Note:** If you get an error below, you may have permission issue on the folder **certs**.
Make sure that owner of the folder **certs** is the 'openldap' with permission 755.
If you do not have a folder **certs** you should create and move the ldap.homelab.io.pem, ldap.homelab.io.key, CA.pem  to **certs** folder.

```console
SASL/EXTERNAL authentication started
SASL username: gidNumber=0+uidNumber=0,cn=peercred,cn=external,cn=auth
SASL SSF: 0
modifying entry "cn=config"
ldap_modify: Other (e.g., implementation specific) error (80)
```

```shell
root@ldap:~# mkdir -o /etc/ldap/certs
root@ldap:~# chown -R openldap.openldap /etc/ldap/certs
root@ldap:~# chmod -R 755  /etc/ldap/certs
```

**Note:** You may also get the following error. During the test after configure
implement SSL configuration to OpenLDAP server.

```shell
ldapwhoami -H ldap:// -x -ZZ
ldap_start_tls: Connect error (-11)
	additional info: (unknown error code)
```

**Solution:** Do not forget to add CA.pem to ldap server truststore
dpkg-reconfigure ca-Certificates

```shell
root@ldap:~# cp myCA.crt /usr/share/ca-certificates/
root@ldap:~# dpkg-reconfigure ca-certificates
```

**Note:** If you do not configure your dns or /etc/hosts files that is the same as
ldapserver CN name, you may also get following error.

```shell
ldapwhoami -H ldap:// -x -ZZ
ldap_start_tls: Connect error (-11)
	additional info: TLS: hostname does not match CN in peer certificate
```

**Solution**
[Reference](https://www.openldap.org/doc/admin24/tls.html#:~:text=Server%20Certificates,certificate%20names%20are%20in%20RFC4513.)

# OR (For without DNS)

```shell
root@ldap:~# cat /etc/hosts
127.0.1.1 ldap.homelab.io ldap
127.0.0.1 localhost
```
###### Verify(On OpenLDAP server)
```shell
ldapwhoami -H ldap:// -x -ZZ
anonymous
```

**Important** Even you configured OpenLDAP server with SSL, it does not mean that LDAP client will establish a secure connection to OpenLDAP server. In order to prevent that, we need to configure OpenLDAP server to force STARTTLS otherwise, teardown the connection.

###### Force to use STARTTLS

```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 01-force-starttls.ldif
```

**Verify(on LDAP client)**

```shell
openssl s_client  -connect ldap://ldap.homelab.io  -starttls ldap
#OR(with CA)
openssl s_client  -connect ldap://ldap.homelab.io  -starttls  ldap  -CAfile CA/myCA.pem
```

**ldapsearch without startls**

```shell
ldapsearch -H ldap://ldap.homelab.io -b "cn=admin,dc=homelab,dc=io"  -x
# extended LDIF
#
# LDAPv3
# base <cn=admin,dc=homelab,dc=io> with scope subtree
# filter: (objectclass=*)
# requesting: ALL
#

# search result
search: 2
result: 13 Confidentiality required
text: TLS confidentiality required

# numResponses: 1
```

###### Changing CipherSuite(Not Implemented)
For more secure option, you can also configure CipherSuites based on your company's policy.

```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 01-_changeCipherSuite.ldif
```

##### Disabling Anonymous Bind
Anonymous bind is a Bind Request using Simple Authentication with a zero-length bind DN and/or a zero-length password.
It is best practice to disable Anonymous Bind otherwise unauthenticated user may get curial information about your company.

```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 05-disable-anonbind.ldif
```

**Verify Anonymous Bind**
```shell
ldapsearch -H ldap://ldap.homelab.io -b "dc=homelab,dc=io"   -x -Z
ldap_bind: Inappropriate authentication (48)
	additional info: anonymous bind disallowed
┌─[✗]─[goki@parrot]─[~]
└──╼ $
```

###### Enable Logging on OpenLDAP Server
It may sometimes be important to enable logging on OpenLDAP server for troubleshooting. After enabling following configurations, logs will be written to syslog.
```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 03-enablelogging.ldif
```

###### Changing RootDN
It is good practice  to change default RootDN.

**Regardless of what access control policy is defined, the rootdn is always allowed full rights (i.e. auth, search, compare, read and write) on everything and anything.**

```shell
root@ldap:~# ldapmodify -H ldapi:// -Y EXTERNAL -f 02-change-rootdn.ldif
```

###### Create a Bind User.
By default, bindDN is cn=admin,dc=homelab,dc=io for me. dc entries change based on your domain. You can also create additional bind user and give certain privileges.

```shell
root@ldap:~# ldapadd -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f 04-binduser.ldif -Z
Enter LDAP Password:
adding new entry "cn=Technischeruser,dc=homelab,dc=io"
```
**Note:** After creating a bindDN  you may also need to configure ACL on OpenLDAP server.
You can get yourself familiarize to ACL in the [link](https://www.openldap.org/doc/admin24/access-control.html)

We have almost finished initial configuration and hardening of OpenLDAP Server. Next step is to building LDAP directories in comply with organization. As an example I will create following directories(ou, users, groups) on OpenLDAP.

#### Creating an Organizational Unit (OU)
Following OU will be created.

```console
ou=global,ou=os,ou=posixgroups,dc=homelab,dc=io
ou=global,ou=vault,ou=groups,dc=homelab,dc=io
ou=global,ou=vault,groups=groups,dc=homelab,dc=io
ou=people,ou=it,dc=homelab,dc=io
```

```shell
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f 06-posixOU.ldif
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f 07-vaultOU.ldif
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f 08-peopleOU.ldif
```

#### Creating LDAP Users
Following LDAP users will be created. Users created here are non-posix users.

```console
cn=mit001,ou=people,ou=it,dc=homelab,dc=io
cn=mit002,ou=people,ou=it,dc=homelab,dc=io
```

```shell
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f  09-adduser.ldif
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f  09_-adduser.ldif
```

#### Creating LDAP Groups

Following LDAP groups will be created. Groups created here are non-posix groups.

```console
cn=linux_admin,ou=global,ou=os,ou=posixgroups,dc=homelab,dc=io #posixGroup
cn=vault_admin,ou=global,ou=vault,ou=groups,dc=homelab,dc=io   #non-posixGroup
cn=vault_user,ou=global,ou=vault,groups=groups,dc=homelab,dc=io #non-posixGroup
```

```shell
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f  10-addgroup.ldif
root@ldap:~# ldapadd -Z -W -x -D "cn=ldapadm,ou=users,dc=homelab,dc=io" -f  10_-addgroup.ldif
```

We successfully created our Organization, Groups and Users. You can create more Organization, Groups and Users. It is all up to you. But consider not to too overcomplicated.


Next post will be integrating HashiCorp Vault with OpenLDAP server and mapping HashiCorp Vault policies with OpenLDAP groups.

