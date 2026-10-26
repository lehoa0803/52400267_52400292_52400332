(async function renderTripExport() {
  'use strict';
  const status=document.getElementById('status'), button=document.getElementById('downloadPdf');
  try {
    if(document.readyState==='loading')await new Promise(resolve=>document.addEventListener('DOMContentLoaded',resolve,{once:true}));
    const tt=value=>window.tcT?window.tcT(value):value;
    const data=JSON.parse(document.getElementById('exportData').textContent);
    await document.fonts.ready;
    const width=1000,height=1414,margin=65,bottom=height-80,pages=[];
    let ctx,y;
    function page(){const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;ctx=canvas.getContext('2d');if(!ctx)throw Error('Trình duyệt không hỗ trợ xuất ảnh.');ctx.fillStyle='white';ctx.fillRect(0,0,width,height);ctx.fillStyle='#0f6e66';ctx.fillRect(0,0,width,48);ctx.font='bold 20px Arial';ctx.fillStyle='white';ctx.fillText('TripConnect · '+tt('Lịch trình'),margin,32);y=90;pages.push(canvas);}
    function line(text,size,bold,color){if(y+size*1.5>bottom)page();ctx.font=(bold?'bold ':'')+size+'px Arial';ctx.fillStyle=color;ctx.fillText(text,margin,y);y+=size*1.5;}
    function paragraph(text,size=20,bold=false,color='#233f3d'){
      ctx.font=(bold?'bold ':'')+size+'px Arial';
      for(const block of String(text||'').split(/\r?\n/)){
        let current='';
        for(const word of block.split(/\s+/)){
          const candidate=current?current+' '+word:word;
          if(ctx.measureText(candidate).width<=width-2*margin){current=candidate;continue;}
          if(current){line(current,size,bold,color);current='';ctx.font=(bold?'bold ':'')+size+'px Arial';}
          for(const character of Array.from(word)){
            if(ctx.measureText(current+character).width>width-2*margin){line(current,size,bold,color);current='';ctx.font=(bold?'bold ':'')+size+'px Arial';}
            current+=character;
          }
        }
        line(current,size,bold,color);
      }
      y+=10;
    }
    const compose=parts=>parts.map(part=>part.ui!==undefined?tt(part.ui):part.money!==undefined?new Intl.NumberFormat(localStorage.getItem('tripconnect_lang')==='en'?'en-US':'vi-VN',{maximumFractionDigits:2}).format(part.money)+' '+tt('đ'):part.text).join('');
    page();paragraph(data.title,32,true,'#0f6e66');
    for(const section of data.sections){if(y+110>bottom)page();paragraph(section.titleParts?compose(section.titleParts):tt(section.title),25,true,'#0f6e66');for(const [index,text] of section.lines.entries())paragraph(section.lineParts?.[index]?compose(section.lineParts[index]):text);y+=12;}
    const encoder=new TextEncoder();
    function pdf(){
      const chunks=[],offsets=[0];let length=0;
      function append(value){const bytes=typeof value==='string'?encoder.encode(value):value;chunks.push(bytes);length+=bytes.length;}
      function object(id,body){offsets[id]=length;append(id+' 0 obj\n');for(const piece of body)append(piece);append('\nendobj\n');}
      append('%PDF-1.4\n');object(1,['<< /Type /Catalog /Pages 2 0 R >>']);object(2,['<< /Type /Pages /Count '+pages.length+' /Kids ['+pages.map((_,i)=>(3+i*3)+' 0 R').join(' ')+'] >>']);
      pages.forEach((canvas,i)=>{
        const id=3+i*3,raw=atob(canvas.toDataURL('image/jpeg',0.95).split(',')[1]),jpeg=Uint8Array.from(raw,c=>c.charCodeAt(0)),commands='q 595.28 0 0 841.89 0 0 cm /Im0 Do Q';
        object(id,['<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /XObject << /Im0 '+(id+2)+' 0 R >> >> /Contents '+(id+1)+' 0 R >>']);
        object(id+1,['<< /Length '+encoder.encode(commands).length+' >>\nstream\n'+commands+'\nendstream']);
        object(id+2,['<< /Type /XObject /Subtype /Image /Width '+width+' /Height '+height+' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '+jpeg.length+' >>\nstream\n',jpeg,'\nendstream']);
      });
      const xref=length;append('xref\n0 '+offsets.length+'\n0000000000 65535 f \n');for(let i=1;i<offsets.length;i++)append(String(offsets[i]).padStart(10,'0')+' 00000 n \n');append('trailer\n<< /Size '+offsets.length+' /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF');
      return new Blob(chunks,{type:'application/pdf'});
    }
    function download(blob,name){if(!blob)throw Error(tt('Không tạo được file ảnh.'));const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);status.textContent=tt('Đã tạo file')+' '+name+'. '+tt('Nếu chưa tải được, hãy kiểm tra quyền tải xuống của trình duyệt.');}
    pages.forEach((canvas,i)=>{
      const footer=canvas.getContext('2d');footer.font='17px Arial';footer.fillStyle='#617875';footer.fillText('TripConnect · '+tt('Trang')+' '+(i+1)+' / '+pages.length,margin,height-35);
      const article=document.createElement('article'),png=document.createElement('button');png.type='button';png.textContent='Tải ảnh PNG — trang '+(i+1)+' / '+pages.length;png.addEventListener('click',()=>{try{canvas.toBlob(blob=>{try{download(blob,'trip-'+data.id+'-page-'+(i+1)+'.png');}catch(e){status.textContent=e.message;}},'image/png');}catch(e){status.textContent=e.message;}});article.append(png,canvas);png.setAttribute('data-i18n-ui','');document.getElementById('pages').appendChild(article);
    });
    window.tripExport={pages,makePdf:pdf};
    button.disabled=false;button.textContent='Tải PDF ('+pages.length+' trang)';button.addEventListener('click',()=>{button.disabled=true;try{download(pdf(),'trip-'+data.id+'.pdf');}catch(e){status.textContent='Không thể tạo PDF: '+e.message;}finally{button.disabled=false;}});
    status.textContent=tt('Sẵn sàng')+': '+pages.length+' '+tt('trang')+'. '+tt('PNG được chia từng trang để không cắt nội dung.');
    window.tripExportLanguage=localStorage.getItem('tripconnect_lang')||'vi';
    if(!window.tripExportLanguageBound){window.tripExportLanguageBound=true;document.addEventListener('tripconnect:languagechange',event=>{if(event.detail.lang===window.tripExportLanguage)return;document.getElementById('pages').replaceChildren();renderTripExport();});}
  } catch(e){const translate=window.tcT||String;status.textContent=translate('Không thể xuất:')+' '+translate(e.message)+' '+translate('Hãy tải lại trang hoặc thử trình duyệt khác.');button.disabled=true;}
}());
