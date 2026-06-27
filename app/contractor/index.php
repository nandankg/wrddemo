<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Contractor self-registration (creates contractor + application at ASO).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register' && $role==='CONTRACTOR') {
  $class=in_array($_POST['class']??'',['I','II','III','IV'],true)?$_POST['class']:'IV';
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM contractors')->fetchColumn()+451;
  $reg=sprintf('WRD/REG/3/%04d',$cnt);
  $qr=bin2hex(random_bytes(6));
  $pdo->prepare("INSERT INTO contractors (reg_no,name,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token,login_user,cin,address,contact,experience_yrs,completed_projects,turnover) VALUES (?,?,?,?,?,?, 'Pending',?,?,CURDATE(),?,?,?,?,?,?,?,?)")
      ->execute([$reg,trim($_POST['name']),$class,strtoupper(trim($_POST['pan'])),strtoupper(trim($_POST['gst'])),trim($_POST['district']),rand(15,40),date('Y-m-d',strtotime('+3 years')),$qr,$u['username'],
                 strtoupper(trim($_POST['cin']??'')),trim($_POST['address']??''),trim($_POST['contact']??''),(int)($_POST['experience_yrs']??0),(int)($_POST['completed_projects']??0),(float)($_POST['turnover']??0)]);
  $cid=(int)$pdo->lastInsertId();
  $ackcnt=(int)$pdo->query('SELECT COUNT(*) FROM contractor_apps')->fetchColumn()+1001;
  $ack=sprintf('WRD/ACK/2526/%04d',$ackcnt);
  $pdo->prepare("INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?, 'New',?,'ASO','Document Verification',?,1,CURDATE())")
      ->execute([$ack,$cid,$class,contractor_fee($class)]);
  add_audit($pdo,'contractor_app',(int)$pdo->lastInsertId(),'Application submitted (Aadhaar e-KYC + DigiLocker)','Citizen','ASO',$actor,'E-GRAS fee paid · Ack '.$ack);
  flash("Registration submitted. Acknowledgement $ack");
  header('Location: index.php'); exit;
}

// Contractor answers an officer query: records the response and resumes the workflow.
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='respond_query' && $role==='CONTRACTOR') {
  $qid=(int)($_POST['query_id']??0); $resp=trim($_POST['response_text']??'');
  $q=$pdo->query("SELECT q.*, a.contractor_id FROM contractor_queries q JOIN contractor_apps a ON a.id=q.app_id WHERE q.id=$qid")->fetch();
  // Only the owning contractor may respond, and only to a still-open query.
  if ($q && $resp!=='' && $q['status']==='Open') {
    $own=$pdo->query("SELECT 1 FROM contractors WHERE id=".(int)$q['contractor_id']." AND login_user=".$pdo->quote($u['username']))->fetchColumn();
    if ($own) {
      $pdo->prepare("UPDATE contractor_queries SET status='Responded', response_text=?, responded_on=CURDATE() WHERE id=?")->execute([$resp,$qid]);
      $pdo->prepare("UPDATE contractor_apps SET status='Under Process' WHERE id=? AND status='Query Raised'")->execute([$q['app_id']]);
      add_audit($pdo,'contractor_app',(int)$q['app_id'],'Query response submitted','Contractor',$q['raised_role'],$actor,$resp);
      flash('Response submitted to the registering authority.');
    }
  }
  header('Location: index.php'); exit;
}

$view = contractor_role_view($role);
if ($view==='contractor') {
  $st=$pdo->prepare("SELECT a.*,c.name cname,c.name_hi cname_hi,c.id cid,c.status cstatus FROM contractor_apps a JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $apps=$st->fetchAll();
  $myq=$pdo->query("SELECT q.* FROM contractor_queries q JOIN contractor_apps a ON a.id=q.app_id JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=".$pdo->quote($u['username'])." AND q.status='Open' ORDER BY q.id DESC")->fetchAll();
  $contractors=[];
} else {
  $apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
  $contractors=$pdo->query("SELECT * FROM contractors")->fetchAll();
  $myq=[];
}
$k=contractor_kpis($apps,$contractors);
$bd = contractor_app_breakdown($apps);
$tasks=contractor_pending_actions($role,$apps);
$STAGES=['ASO','AE','EE','EIC'];

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Registry Desk';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= $view==='contractor'?(is_hi()?'मेरा पंजीकरण':'My Registration'):(is_hi()?'पंजीयन कार्यालय':'Registering Authority Desk') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <?php if($view==='contractor'): ?>
    <button onclick="document.getElementById('wiz').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया पंजीकरण':'New Registration' ?></button>
  <?php endif; ?>
</div>

<?php if($view==='registry'): ?>
<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'प्रक्रियाधीन आवेदन':'Applications In Process', (string)$k['in_process'], 'text-amber-700'],
    [is_hi()?'सक्रिय ठेकेदार':'Active Contractors', (string)$k['active'], 'text-emerald-700'],
    [is_hi()?'ब्लैकलिस्टेड':'Blacklisted', (string)$k['blacklisted'], 'text-rose-700'],
    [is_hi()?'कुल आवेदन':'Total Applications', (string)$k['total_apps'], 'text-ink'],
  ] as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Screen 6: scrutiny pipeline breakdown -->
<div class="card p-5 mb-6">
  <div class="flex items-center gap-2 mb-4">
    <span class="h-5 w-1.5 rounded bg-brand" aria-hidden="true"></span>
    <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'जांच पाइपलाइन':'Scrutiny Pipeline' ?></h2>
  </div>
  <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 text-center">
    <?php foreach ([
        [is_hi()?'नए':'New', $bd['new'], 'text-sky-700'],
        [is_hi()?'सत्यापन':'Verifying', $bd['verifying'], 'text-indigo-700'],
        [is_hi()?'अनुमोदन हेतु':'Approval Pending', $bd['approval_pending'], 'text-violet-700'],
        [is_hi()?'प्रश्न':'Query Raised', $bd['query'], 'text-amber-700'],
        [is_hi()?'स्वीकृत':'Approved', $bd['approved'], 'text-emerald-700'],
        [is_hi()?'अस्वीकृत':'Rejected', $bd['rejected'], 'text-rose-700'],
      ] as $cell): ?>
      <div class="rounded-xl bg-slate-50 py-3">
        <div class="text-2xl font-display font-bold <?= $cell[2] ?>"><?= (int)$cell[1] ?></div>
        <div class="text-[11px] text-slate-500 mt-0.5"><?= e($cell[0]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'मेरे चरण की लंबित फाइलें':'Files Pending at My Stage' ?></h2>
      <a href="<?= base_url('app/contractor/applications.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
    </div>
    <?php if($tasks): ?>
      <div class="space-y-2">
        <?php foreach($tasks as $tk): ?>
          <a href="<?= base_url('app/contractor/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm"><div class="text-4xl mb-2">✓</div><?= is_hi()?'आपके चरण पर कोई लंबित फाइल नहीं।':'No files pending at your stage.' ?><br><span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left).' ?></span></div>
    <?php endif; ?>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'त्वरित लिंक':'Quick Links' ?></h2>
    <a href="<?= base_url('app/contractor/applications.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📋 <?= is_hi()?'आवेदन इनबॉक्स':'Applications Inbox' ?></span></a>
    <a href="<?= base_url('app/contractor/registry.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📒 <?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></span></a>
    <a href="<?= base_url('app/contractor/verify.php') ?>" target="_blank" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper"><span class="font-medium text-slate-700">✔ <?= is_hi()?'प्रमाणपत्र सत्यापन':'Verify Certificate' ?></span></a>
  </div>
</div>

<?php else: // ===== contractor portal ===== ?>
  <?php if (!empty($myq)): ?>
    <div class="card p-6 mb-6 border-l-4 border-amber-400">
      <h2 class="font-display text-lg font-semibold text-ink mb-3">⚠ <?= is_hi()?'विभाग से प्रश्न':'Queries from the Department' ?></h2>
      <?php foreach ($myq as $q): ?>
        <div class="border border-slate-100 rounded-xl p-4 mb-3">
          <p class="text-sm text-slate-700"><?= e($q['query_text']) ?></p>
          <form method="post" class="flex flex-wrap gap-2 mt-3 items-end">
            <input type="hidden" name="query_id" value="<?= e($q['id']) ?>">
            <input name="response_text" required placeholder="<?= is_hi()?'अपना उत्तर लिखें…':'Type your response…' ?>" class="flex-1 min-w-[200px] border border-slate-300 rounded-xl px-3 py-2.5 text-sm">
            <button name="action" value="respond_query" class="btn-acc font-semibold px-4 py-2.5 rounded-xl text-sm"><?= is_hi()?'उत्तर भेजें':'Submit Response' ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<div class="space-y-3">
  <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><div class="font-medium text-slate-800"><?= bi($a['cname'],$a['cname_hi']) ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?></div></div>
        <?= badge($a['status']) ?>
      </div>
      <div class="flex items-center gap-1 mt-3">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i?'text-white':'bg-slate-100 text-slate-400') ?>" <?= $si===$i?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if($a['status']==='Approved'): ?>
        <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= (int)$a['cid'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📄 <?= is_hi()?'प्रमाणपत्र डाउनलोड करें':'Download Certificate' ?> →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$apps): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई आवेदन नहीं। "नया पंजीकरण" से आरंभ करें।':'No applications yet. Start with "New Registration".' ?></div><?php endif; ?>
</div>

<!-- Registration wizard -->
<dialog id="wiz" class="rounded-2xl p-0 w-full max-w-xl backdrop:bg-black/50">
  <form method="post" class="p-6"><input type="hidden" name="action" value="register">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'ठेकेदार पंजीकरण':'Contractor Registration' ?></h2>
      <button type="button" onclick="document.getElementById('wiz').close()" class="text-slate-400 text-xl">✕</button>
    </div>
    <div class="flex items-center gap-1 mb-5" id="steps">
      <?php foreach(['Company','Class','Technical','Financial','Bank','Documents'] as $si=>$ss): ?>
        <div class="flex-1 text-center"><div class="step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold <?= $si===0?'text-white':'bg-slate-100 text-slate-400' ?>" <?= $si===0?'style="background:'.e($APP['accent']).'"':'' ?>><?= $si+1 ?></div><div class="text-[10px] text-slate-400 mt-1"><?= $ss ?></div></div>
      <?php endforeach; ?>
    </div>

    <!-- Step 1: Company Details + Aadhaar e-KYC + DigiLocker -->
    <div class="wiz-pane" data-pane="0">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'आधार ई-केवाईसी सत्यापित':'Aadhaar e-KYC verified' ?></span>
        <button type="button" id="dlBtn" onclick="digilocker()" class="bg-[#06314a] text-white text-xs font-semibold px-3 py-1.5 rounded-lg">📤 <?= is_hi()?'डिजीलॉकर कनेक्ट करें':'Connect DigiLocker' ?></button>
      </div>
      <div id="dlStatus" class="hidden rounded-xl p-3 text-sm mb-3" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 10%,#fff)"></div>
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'फर्म का नाम':'Firm Name' ?></label>
      <input name="name" id="f_name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">PAN</label><input name="pan" id="f_pan" required placeholder="AABCN1234K" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">CIN</label><input name="cin" id="f_cin" placeholder="U45200JH2020PTC0001" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">GSTIN (JH only)</label><input name="gst" id="f_gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p id="gsterr" class="text-xs text-rose-600 mt-1 hidden"><?= is_hi()?'केवल झारखंड (20) जीएसटीआईएन मान्य।':'Only Jharkhand (code 20) GSTIN is valid.' ?></p>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पता':'Address' ?></label><input name="address" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'संपर्क':'Contact' ?></label><input name="contact" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
    </div>

    <!-- Step 2: Classification -->
    <div class="wiz-pane hidden" data-pane="1">
      <p class="text-sm text-slate-500 mb-2"><?= is_hi()?'श्रेणी चुनें — पात्रता मानदंड:':'Choose a class — eligibility criteria:' ?></p>
      <div class="grid grid-cols-2 gap-2 text-xs mb-3">
        <?php foreach([['I','10+ yrs · 10+ proj · ₹5 Cr+','10+ वर्ष · 10+ परियोजना · ₹5 करोड़+'],['II','7+ yrs · 6+ proj · ₹3 Cr+','7+ वर्ष · 6+ परियोजना · ₹3 करोड़+'],['III','4+ yrs · 3+ proj · ₹1.5 Cr+','4+ वर्ष · 3+ परियोजना · ₹1.5 करोड़+'],['IV','Entry level','प्रवेश स्तर']] as $cc): ?>
          <div class="border border-slate-200 rounded-lg px-3 py-2"><b>Class <?= $cc[0] ?></b><div class="text-slate-400"><?= is_hi()?$cc[2]:$cc[1] ?></div></div>
        <?php endforeach; ?>
      </div>
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदित श्रेणी':'Applied Class' ?></label>
      <select name="class" id="f_class" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>I</option><option>II</option><option>III</option><option selected>IV</option></select>
    </div>

    <!-- Step 3: Technical Credentials -->
    <div class="wiz-pane hidden" data-pane="2">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'अनुभव (वर्ष)':'Experience (years)' ?></label><input type="number" name="experience_yrs" id="f_yrs" min="0" value="5" oninput="recomputeElig()" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पूर्ण परियोजनाएँ':'Completed Projects' ?></label><input type="number" name="completed_projects" id="f_proj" min="0" value="5" oninput="recomputeElig()" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p class="text-xs text-slate-400 mt-3"><?= is_hi()?'कार्य आदेश एवं पूर्णता प्रमाणपत्र दस्तावेज़ चरण में अपलोड करें।':'Upload work orders & completion certificates in the Documents step.' ?></p>
    </div>

    <!-- Step 4: Financial Credentials + live eligibility -->
    <div class="wiz-pane hidden" data-pane="3">
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'वार्षिक टर्नओवर (₹)':'Annual Turnover (₹)' ?></label>
      <input type="number" name="turnover" id="f_turn" min="0" step="100000" value="16000000" oninput="recomputeElig()" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <p class="text-xs text-slate-400 mt-1"><?= is_hi()?'आईटी रिटर्न, बैलेंस शीट, सीए प्रमाणपत्र दस्तावेज़ चरण में।':'IT returns, balance sheet & CA certificate in the Documents step.' ?></p>
      <div id="eligBox" class="mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">🤖 <?= is_hi()?'अनुशंसित श्रेणी':'Recommended class' ?>: <span id="eligClass">—</span></div>
    </div>

    <!-- Step 5: Bank Details -->
    <div class="wiz-pane hidden" data-pane="4">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'खाता संख्या':'Account Number' ?></label><input class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">IFSC</label><input placeholder="SBIN0001234" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mt-3 text-sm"><span><?= is_hi()?'रद्द किया गया चेक':'Cancelled Cheque' ?></span><span class="text-emerald-600 text-xs font-semibold">✓ uploaded</span></label>
    </div>

    <!-- Step 6: Documents (drag & drop) + E-GRAS fee -->
    <div class="wiz-pane hidden" data-pane="5">
      <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center text-sm text-slate-500 mb-3">⬆ <?= is_hi()?'दस्तावेज़ यहाँ खींचें और छोड़ें (डेमो)':'Drag & drop documents here (demo)' ?></div>
      <?php foreach([['Photograph','फोटोग्राफ'],['Signature','हस्ताक्षर'],['PAN Card','पैन कार्ड'],['Incorporation Certificate','निगमन प्रमाणपत्र'],['GST Certificate','जीएसटी प्रमाणपत्र'],['Balance Sheet','बैलेंस शीट'],['CA Certificate','सीए प्रमाणपत्र']] as $doc): $v=contractor_doc_verify($doc[0],15); /* fixed demo seed: surfaces one representative document issue per Screen 4 */ ?>
        <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mb-2 text-sm">
          <span><?= is_hi()?$doc[1]:$doc[0] ?></span>
          <?php if($v['status']==='Verified'): ?>
            <span class="text-emerald-600 text-xs font-semibold">🤖 ✓ <?= is_hi()?'सत्यापित':'Verified' ?></span>
          <?php else: ?>
            <span class="text-amber-600 text-xs font-semibold" title="<?= e($v['issue']) ?>">⚠ <?= e($v['issue']) ?></span>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
      <p class="text-[11px] text-slate-400 mb-2">🤖 <?= is_hi()?'एआई दस्तावेज़ जाँच — हस्ताक्षर, तिथि एवं गुणवत्ता।':'AI document check — signature, date & quality.' ?></p>
      <div class="bg-paper rounded-xl p-3 text-sm mt-3 flex justify-between"><span class="text-slate-500"><?= is_hi()?'पंजीकरण शुल्क (ई-ग्रास)':'Registration Fee (E-GRAS)' ?></span><span class="font-semibold" id="feeAmt">₹10,000</span></div>
      <div class="mt-2 text-sm text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2">✓ <?= is_hi()?'भुगतान सफल (डेमो)।':'Payment successful (demo).' ?></div>
    </div>

    <div class="flex gap-2 mt-5">
      <button type="button" id="prevBtn" onclick="wizStep(-1)" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600 hidden"><?= is_hi()?'पीछे':'Back' ?></button>
      <button type="button" id="nextBtn" onclick="wizStep(1)" class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आगे':'Next' ?> →</button>
      <button type="submit" id="subBtn" class="flex-1 bg-emerald-600 text-white rounded-xl py-2.5 font-semibold hidden"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
    </div>
  </form>
</dialog>
<script>
let cur=0; const panes=document.querySelectorAll('.wiz-pane'), dots=document.querySelectorAll('.step-dot');
const LAST=5, ACC='<?= e($APP['accent']) ?>';
function recomputeElig(){
  const y=+document.getElementById('f_yrs').value||0, p=+document.getElementById('f_proj').value||0, t=+document.getElementById('f_turn').value||0;
  let cls='IV';
  if(y>=10&&p>=10&&t>=50000000) cls='I'; else if(y>=7&&p>=6&&t>=30000000) cls='II'; else if(y>=4&&p>=3&&t>=15000000) cls='III';
  document.getElementById('eligClass').textContent='Class '+cls;
}
function wizStep(dir){
  document.getElementById('gsterr').classList.add('hidden');
  if(dir>0 && cur===0){ const g=document.getElementById('f_gst').value.trim();
    if(g && !g.startsWith('20')){ document.getElementById('gsterr').classList.remove('hidden'); return; } }
  cur=Math.max(0,Math.min(LAST,cur+dir));
  panes.forEach((p,i)=>p.classList.toggle('hidden',i!==cur));
  dots.forEach((d,i)=>{ d.className='step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold '+(i<=cur?'text-white':'bg-slate-100 text-slate-400'); d.style.background=i<=cur?ACC:''; });
  document.getElementById('prevBtn').classList.toggle('hidden',cur===0);
  document.getElementById('nextBtn').classList.toggle('hidden',cur===LAST);
  document.getElementById('subBtn').classList.toggle('hidden',cur!==LAST);
  const fees={'I':'₹45,000','II':'₹30,000','III':'₹20,000','IV':'₹10,000'};
  document.getElementById('feeAmt').textContent=fees[document.getElementById('f_class').value]||'₹10,000';
  if(cur===3) recomputeElig();
}
function digilocker(){
  const btn=document.getElementById('dlBtn'); if(btn.disabled) return; btn.disabled=true;
  const box=document.getElementById('dlStatus'); box.classList.remove('hidden');
  box.innerHTML='⏳ Connecting to DigiLocker…';
  const items=['PAN','Aadhaar','GST','Company Documents']; let i=0;
  const tick=setInterval(()=>{ i++; box.innerHTML='Fetching: '+items.slice(0,i).map(x=>'✓ '+x).join('  ');
    if(i>=items.length){ clearInterval(tick);
      document.getElementById('f_name').value='M/s ABC Infra Pvt Ltd';
      document.getElementById('f_pan').value='AABCA9999K';
      document.getElementById('f_gst').value='20ABCIN9999A1Z5';
      document.getElementById('f_cin').value='U45200JH2021PTC009999';
      box.innerHTML='✅ <b>Verification Successful</b> — details auto-filled from DigiLocker.';
    } },500);
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
