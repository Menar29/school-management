<?php if ($codes): ?>
  <div>
    <h1 class="h1" style="font-size:28px;line-height:36px">Codes de secours</h1>
    <p class="muted" style="margin-top:4px">Si vous perdez votre téléphone, chacun de ces codes permet une connexion. Notez-les sur papier et rangez-les en lieu sûr : ils ne seront plus jamais affichés.</p>
  </div>
  <div class="card card-b" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-family:ui-monospace,Menlo,monospace;font-size:18px;text-align:center">
    <?php foreach ($codes as $c): ?><span><?= e($c) ?></span><?php endforeach; ?>
  </div>
  <form method="post" action="<?= e(url('deux-etapes/config')) ?>"><?= csrf_field() ?><input type="hidden" name="etape" value="fin">
    <button class="btn pri xl block" type="submit">J'ai noté mes codes, continuer</button>
  </form>
<?php else: ?>
  <div>
    <h1 class="h1" style="font-size:28px;line-height:36px">Activer la vérification en deux étapes</h1>
    <p class="muted" style="margin-top:4px">Obligatoire pour le propriétaire et les gérants. Installez Google Authenticator ou Microsoft Authenticator sur votre téléphone, puis scannez ce code.</p>
  </div>
  <?php if ($err): ?><div class="banner bad" role="alert"><?= e($err) ?></div><?php endif; ?>
  <div class="card card-b" style="display:flex;flex-direction:column;align-items:center;gap:12px">
    <div id="qr" data-qr="<?= e($uri) ?>" style="width:200px;height:200px"></div>
    <p class="small muted" style="text-align:center">Impossible de scanner ? Saisissez cette clé dans l'application :</p>
    <code style="font-size:15px;letter-spacing:.08em;word-break:break-all;text-align:center"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
  </div>
  <form method="post" action="<?= e(url('deux-etapes/config')) ?>" class="stack">
    <?= csrf_field() ?><input type="hidden" name="etape" value="verifier">
    <div>
      <label class="lbl" for="code">Code affiché par l'application</label>
      <input id="code" name="code" class="inp num otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
    </div>
    <button class="btn pri xl block" type="submit">Vérifier et activer</button>
  </form>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>document.addEventListener('DOMContentLoaded',()=>{const el=document.getElementById('qr');if(window.QRCode)new QRCode(el,{text:el.dataset.qr,width:200,height:200});});</script>
<?php endif; ?>
