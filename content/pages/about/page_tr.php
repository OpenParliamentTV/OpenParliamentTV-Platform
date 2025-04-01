<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

include_once(__DIR__ . '/../../header.php');
?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::about; ?></h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12 col-lg-8">
			<p>Bu arama motoru ve etkileşimli video platformu, <a href="https://openparliament.tv">Open Parliament TV</a> projesinin bir parçasıdır. Amacımız <b>parlamento tartışmalarını daha şeffaf ve erişilebilir hale getirmektir</b>.</p>
			<p>Open Parliament TV ile, siyasi konuşmaları deneyimlemenin yeni yollarını kolaylaştırmak için gerekli süreçleri, araçları ve kullanıcı arayüzlerini geliştiriyoruz. Yaklaşımımızın merkezinde, <b>video kayıtlarını</b> <b>tutanaklarla</b> <b>senkronize ediyoruz</b>. Bu sayede videolar için tam metin araması sağlayabiliyoruz.</p>
			<p>Video kaydını tutanak metniyle bağlayarak, videoları şunlarla zenginleştirebiliyoruz:</p>
			<ul>
				<li><b>etkileşimli transkripsiyonlar</b> <br>(bir cümleye tıklayın > videoda ilgili ana atlayın)</li>
				<li><b>bağlamsal açıklamalar</b> <br>(belirli anlarda ilgili belgelerin gösterimi)</li>
				<li>geliştirilmiş <b>katılım</b> araçları <br>(belirli video bölümlerini tartışma, alıntılama ve paylaşma)</li>
			</ul>
			<p>Open Parliament TV ile, vatandaşlara ve gazetecilere parlamenter konuşmaların video alıntılarını <b>arama</b>, <b>paylaşma</b> ve <b>alıntılama</b>yı önemli ölçüde kolaylaştıran bir araç sunuyoruz. Tek tek terimlere veya cümle parçalarına dayanarak, ilgili video alıntıları saniyeler içinde bulunabilir, izlenebilir ve diğer platformlarda alıntı olarak entegre edilebilir.</p>
			<p>Tam metin aramasının yanı sıra, Open Parliament TV, bir parlamento grubunun profil sayfası üzerinden konuşma araması veya belirli bir belge veya yasanın bahsedildiği tüm konuşmaları izleme gibi tartışmalara <b>ek giriş noktaları</b> sunuyor. Gelecekte, bu özellikleri genel kurul tutanaklarının yarı otomatik analizleriyle genişletmek istiyoruz.</p>
			<a href="https://openparliament.tv" target="_blank" class="btn btn-primary btn-sm d-block"><span class="icon-right-open-big me-1"></span> Open Parliament TV'nin vizyonu, misyonu, stratejisi ve uygulama alanları hakkında daha fazla bilgi edinin</a>
			<hr>
			<h3>Açık Veri</h3>
			<p>Open Parliament TV'deki tüm veriler <b>Açık Veri API'miz</b> üzerinden talep edilebilir:</p>
			<ul>
				<li><a href="api">API Dokümantasyonu</a></li>
			</ul>
			<hr>
			<h3>Açık Kaynak</h3>
			<p>Open Parliament TV <b>ticari olmayan bir Açık Kaynak projesidir</b>. Projenin tüm bileşenleri Github'da <b>özgür lisanslar</b> altında yayınlanmıştır:</p>
			<ul>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
			</ul>
			<hr>
			<h3>SSS</h3>
			<p>Proje, verilerimiz veya teknik özellikler hakkındaki sorular <a href="https://openparliament.tv/faq">Sıkça Sorulan Sorular</a> bölümümüzde yanıtlanmaktadır.</p>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>İletişim & Talepler</h3>
			<p>Joscha Jäger, Kurucu & Proje Yöneticisi<br>
			E-posta: joscha.jaeger [AT] openparliament.tv<br>
			Twitter: <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
		</div>
	</div>
</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?> 