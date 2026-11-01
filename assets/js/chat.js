(() => {
 'use strict';
 const box=document.querySelector('[data-chat-messages]');if(!box||!window.fetch)return;
 let last=Number(box.dataset.chatLast)||0,busy=false;
 const poll=async()=>{
  if(busy||document.hidden)return;busy=true;
  try{
   const response=await fetch(box.dataset.chatEndpoint+'&after='+last,{credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))return;
   const data=await response.json();if(!Array.isArray(data.messages))return;
   for(const message of data.messages){
    if(!Number.isInteger(message.id)||message.id<=last||typeof message.message_text!=='string')continue;
    const item=document.createElement('article');item.className='panel';item.dataset.chatMessage=String(message.id);
    const author=document.createElement('strong');author.textContent=Number(message.sender_id)===Number(box.dataset.chatSelf)?'Du':'Neue Nachricht';
    const body=document.createElement('p');body.style.whiteSpace='pre-wrap';body.style.overflowWrap='anywhere';body.textContent=message.message_text;
    const date=document.createElement('small');date.textContent=message.created_at+' UTC';item.append(author,body,date);box.append(item);last=message.id;
   }
  }catch(_){/* Native reload and forms remain usable during connection errors. */}finally{busy=false;}
 };
 setInterval(poll,8000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
})();
