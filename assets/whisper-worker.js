let pipe=null;
let currentModel='Xenova/whisper-tiny.en';
async function loadTransformers(){
  // jsDelivr +esm is browser/worker friendly and avoids bare-module resolution problems.
  return await import('https://cdn.jsdelivr.net/npm/@xenova/transformers@2.17.2/+esm');
}
self.onmessage=async e=>{
  const d=e.data||{};
  try{
    if(d.type==='init'){
      currentModel=d.model||currentModel;
      self.postMessage({type:'progress',message:'Loading Whisper engine…'});
      const mod=await loadTransformers();
      mod.env.allowLocalModels=false;
      mod.env.useBrowserCache=true;
      // Keep inference off the page's main thread. Quantized tiny model is the lowest practical CPU/RAM option.
      pipe=await mod.pipeline('automatic-speech-recognition',currentModel,{quantized:true});
      self.postMessage({type:'ready'});
      return;
    }
    if(d.type==='transcribe'){
      if(!pipe)throw new Error('Whisper model is not ready');
      const audio=new Float32Array(d.audio);
      const lm={en:'english',lv:'latvian',ru:'russian',de:'german',fr:'french',es:'spanish',it:'italian',pl:'polish',uk:'ukrainian',lt:'lithuanian',et:'estonian',da:'danish',sv:'swedish',no:'norwegian',fi:'finnish'};const lang=(d.language&&d.language!=='auto')?(lm[d.language]||d.language):'english';
      const result=await pipe(audio,{chunk_length_s:14,stride_length_s:4,language:lang,task:'transcribe',return_timestamps:false,temperature:0});
      self.postMessage({type:'result',text:String(result?.text||'')});
    }
  }catch(err){self.postMessage({type:'error',error:err?.message||String(err)})}
};
