</main>
<div id="toast" class="toast" hidden></div>
<div id="modalRoot"></div>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php if (!empty($pageScripts)): foreach ((array) $pageScripts as $script): ?>
<script src="<?= e(asset_url('js/' . $script)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
