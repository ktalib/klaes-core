# Builds a browser fixture from the actual batch form functions. No live requests.
from pathlib import Path
import re, sys
root = Path(__file__).resolve().parents[2]
source = (root / 'resources/views/land_recommendations/form.blade.php').read_text(encoding='utf-8')
def function(name):
    start = source.index('    function ' + name + '(')
    return source[start:source.index('\n    }', start) + 6]
fields = re.search(r'id="grant-card-apply-all"\s+data-card-fields="([^"]+)"', source)[1].split(',')
html = '<html><body><form id="form">' + ''.join('<input name="' + f + '">' for f in fields) + '</form><button id="grant-card-apply-all" data-card-fields="' + ','.join(fields) + '">Apply to all</button><table><tbody id="rows"></tbody></table><div id="posted"></div><script>'
html += 'var PER_FILE_FIELDS = ' + repr(fields) + ';'
html += """
var recForm=document.getElementById('form'), rowsBody=document.getElementById('rows'), grantInputs=document.getElementById('posted');
var grantStore=[], grantCache={}, grantIndex=0, savedGrant={}, toggle={checked:true}, BATCH_EDIT=false, editDirty=false;
var perFileOn=()=>true, renderGrantStep=()=>{}, setStatus=()=>{}, scheduleSave=()=>{};
var esc=v=>String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
var approved=true;window.confirm=()=>approved;
"""
html += '\n'.join(function(n) for n in ['grantEls','readGrantCard','writeGrantCard','commitGrant','gotoGrant','syncGrantInputs','bindCardApplyAll'])
html += """
bindCardApplyAll('grant-card-apply-all','Grant Conditions');
try {
 for (const issued of [false,true]) {
  BATCH_EDIT=issued; editDirty=false; grantStore=[];savedGrant={};grantIndex=0;rowsBody.innerHTML='';
  for(let i=0;i<79;i++){
   const file='CON-RES-2026-'+(2925+i);
   const g=Object.fromEntries(PER_FILE_FIELDS.map(f=>[f, f==='term'?(i===0?'99':'40'):(i===0?'2026':'2020')]));
   g.__file=file;g.__rowIndex=String(i);grantStore.push(g);savedGrant[file]={...g};
   rowsBody.insertAdjacentHTML('beforeend','<tr class="batch-row" data-index="'+i+'" data-rofo-locked="'+(issued?'1':'0')+'"></tr>');
  }
  writeGrantCard(grantStore[0]);
  approved=false;document.getElementById('grant-card-apply-all').click();
  if(grantStore[1].term!=='40')throw Error('Cancel copied values');
  approved=true;document.getElementById('grant-card-apply-all').click();
  for(let i=0;i<79;i++){
   gotoGrant(i);
   if(recForm.querySelector('[name="term"]').value!=='99')throw Error('Term not 99 for row '+i+' issued='+issued);
   for(const f of PER_FILE_FIELDS)if(grantStore[i][f]!==grantStore[0][f])throw Error('Field not copied: '+f);
  }
  gotoGrant(0);syncGrantInputs();
  if(issued){
   for(let i=1;i<79;i++){
    const term=grantInputs.querySelector('[name="children['+i+'][term]"]');
    if(!term||term.value!=='99')throw Error('Missing issued correction '+i);
   }
   if(!editDirty)throw Error('Edit not marked dirty');
  }else if(grantInputs.querySelector('[name$="[term]"]'))throw Error('Unnecessary term overrides');
 }
 document.body.dataset.result='PASS: 79-file create/edit copy, navigation, cancellation and posted corrections';
}catch(e){document.body.dataset.result='FAIL: '+e.message;}
</script></body></html>
"""
Path(sys.argv[1]).write_text(html, encoding='utf-8')
