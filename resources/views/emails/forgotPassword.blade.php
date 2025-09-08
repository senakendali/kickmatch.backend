<?php 
$url = "https://dev.kickmatch.co.id/"; 
$email = "support@dev.kickmatch.co.id";
$hp = "0812-3315-8881";
?>

<center><h1>Reset Password</h1></center>

<h3>Halo {{$data['email']}},</h3>

<p>Kami menerima permintaan untuk reset password akun KickMatch Anda</p>

<p>Untuk reset password Anda, silahkan klik tombol Reset Password / Tautan dibawah ini, atau Anda bisa salin URL ke browser Anda</p>

<br/>

<center><a href="<?php echo $url; ?>reset-password/{{$data['weburl']}}"><input type="button" value="Reset Password"/></a></center> 

<br/>

<p>Link Tautan : <a href="<?php echo $url; ?>reset-password/{{$data['weburl']}}" target="_ublank"><?php echo $url; ?>reset-password/{{$data['weburl']}}</a></p>

<p>Jika Anda mengalami kendala, silahkan hubungi kami (CS) di : <?php echo $hp; ?> atau </p>
<p>Email ke : <?php echo $email; ?></p>

