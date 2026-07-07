<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?= $e($brand['name'] ?? 'iRedPanel') ?> - <?= $e($pageTitle) ?></title>
    <meta name="description" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/static/chota.min.css" />
    <link rel="stylesheet" href="/static/styles.css" />
    <?php if (!empty($brand['primaryColor'])): ?>
    <style>:root { --color-primary: <?= $e($brand['primaryColor']) ?>; }</style>
    <?php endif; ?>
  </head>
  <body>
    <nav class="nav">
      <div class="nav-left">
        <a class="brand" href="/"><img src="<?= $e($brand['logoUrl'] ?? '/static/logo-iredmail.png') ?>" alt="logo" /> <?= $e($brand['name'] ?? 'iRedPanel') ?></a>
        <?php if (!empty($session['email'])): ?>
        <a href="/dashboard">Dashboard</a>
        <a href="/domains">Domains</a>
        <a href="/search">Search</a>
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <a href="/aliases">Mail Aliases</a>
        <a href="/mailing-lists">Mailing Lists</a>
        <a href="/domain-aliases">Domain Aliases</a>
        <a href="/admins">Admins</a>
        <a href="/logs">Logs</a>
        <?php if (!empty($features['amavisd'])): ?>
        <a href="/amavisd/quarantine">Quarantine</a>
        <a href="/amavisd/spam-policy">Spam Policy</a>
        <a href="/amavisd/wblist">W/B List</a>
        <?php endif; ?>
        <?php if (!empty($features['fail2ban'])): ?>
        <a href="/fail2ban">Fail2ban</a>
        <?php endif; ?>
        <?php if (!empty($features['iredapd'])): ?>
        <a href="/iredapd/throttle/@.">iRedAPD</a>
        <?php endif; ?>
        <a href="/deleted-mailboxes">Deleted Mailboxes</a>
        <a href="/panel-settings">Panel Settings</a>
        <a href="/system-settings">System</a>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="nav-right">
        <?php if (!empty($session['email'])): ?>
        <form method="post" action="/logout" style="display:inline;margin:0"><?= $csrfField ?><button type="submit" class="button outline">Logout <?= $e($session['email']) ?></button></form>
        <?php endif; ?>
      </div>
    </nav>
    <?= $bodyContent ?>
    <footer class="footer">
      <div class="container">
        <?php if (!empty($brand['footerText'])): ?>
        <span class="text-light"><?= $e($brand['footerText']) ?></span> |
        <?php endif; ?>
        <a href="https://github.com/KilimcininKorOglu/iRedPanel" class="text-light" target="_blank"><?= $e($brand['name'] ?? 'iRedPanel') ?> v<?= defined('APP_VERSION') ? APP_VERSION : '0.0.0' ?></a>
      </div>
    </footer>
    <script>
    function generatePassword(){
      var p=<?= $passwordPolicy ?? '{}' ?>;
      var lc='abcdefghjkmnpqrstuvwxyz',uc='ABCDEFGHJKLMNPQRSTUVWXYZ',dg='23456789',sp='$@#%!^&*()-_+={}[]';
      var req=[],pool='';
      if(p.lowercase){req.push(lc[Math.floor(Math.random()*lc.length)]);pool+=lc;}
      if(p.uppercase){req.push(uc[Math.floor(Math.random()*uc.length)]);pool+=uc;}
      if(p.numbers){req.push(dg[Math.floor(Math.random()*dg.length)]);pool+=dg;}
      if(p.special){req.push(sp[Math.floor(Math.random()*sp.length)]);pool+=sp;}
      if(!pool)pool=lc+uc+dg;
      var len=Math.max(p.minLength||8,16),pw=req.slice();
      for(var i=pw.length;i<len;i++)pw.push(pool[Math.floor(Math.random()*pool.length)]);
      for(var i=pw.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=pw[i];pw[i]=pw[j];pw[j]=t;}
      var result=pw.join('');
      var f1=document.getElementById('password'),f2=document.getElementById('password_repeat');
      if(f1){f1.value=result;f1.type='text';setTimeout(function(){f1.type='password';},3000);}
      if(f2)f2.value=result;
    }
    document.querySelectorAll('[data-confirm]').forEach(function(el){
      el.addEventListener(el.tagName==='FORM'?'submit':'click',function(e){
        if(!confirm(el.dataset.confirm))e.preventDefault();
      });
    });
    </script>
  </body>
</html>
