<?php defined('OPTV') or die(); ?>
<?php $this->layout('base') ?>
<?php $this->insert('header') ?>

<?= $this->section('content') ?>

<?php $this->insert('footer') ?>
<?php $this->insert('components/core-scripts') ?>
