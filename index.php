<?php
/**
 * Local XAMPP only. On Namecheap the document root is public_html,
 * so this file sits outside the web root and is never served.
 */
header('Location: public_html/', true, 302);
exit;
