```apache
<VirtualHost *:80>
	ServerName EXAMPLE.COM

	DocumentRoot "/usr/home/eyetea/public_html/EXAMPLE.COM/public/"

	CustomLog "| /usr/local/sbin/rotatelogs -f /var/log/httpd/eyetea/EXAMPLE.COM-access-%Y-%m.log 10M" combined-vhost
	ErrorLog  "| /usr/local/sbin/rotatelogs -f /var/log/httpd/eyetea/EXAMPLE.COM-error-%Y-%m.log  10M"

	<FilesMatch \.php$>
		SetHandler "proxy:fcgi://localhost:9000"
	</FilesMatch>

	<Location />
		Require ip 10.7.12.124
	</Location>

	<Directory "/usr/home/eyetea/public_html/EXAMPLE.COM/public/">
		Options FollowSymLinks
		AllowOverride All
		Require all granted
	</Directory>
</VirtualHost>
```
