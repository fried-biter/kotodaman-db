<script>
    const kotoColumnConfig = <?php echo json_encode(koto_get_column_config()); ?>;
</script>
<?php
// lib/character-search/searchform-content.php を呼び出す
get_template_part('lib/character-search/searchform-content');
