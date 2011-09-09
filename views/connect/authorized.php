<!DOCTYPE html>
<html>
<head>
	<title><?php echo $title; ?></title>
</head>
<body>
<?php if ($show_pages): ?>
<?php else: ?>
<script type="text/javascript">
	window.opener.reloadSocialHTML(<?php echo (!$save ? 'false' : 'true'); ?>;
</script>
<?php endif; ?>
</body>
</html>