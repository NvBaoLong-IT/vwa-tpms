import sys,json,base64,io
from pypdf import PdfReader
r=PdfReader(sys.argv[1])
if len(r.pages)>80:
    raise ValueError('PDF exceeds 80 pages')
if any((p.extract_text() or '').strip() for p in r.pages):
    print(json.dumps({'pages':[]}))
else:
    result=[]
    for number,p in enumerate(r.pages,1):
        images=list(p.images)
        if len(images)!=1:
            raise ValueError('Scan page requires exactly one image')
        im=images[0].image.convert('RGB')
        if p.rotation:
            im=im.rotate(-p.rotation,expand=True)
        im.thumbnail((2000,2400))
        out=io.BytesIO()
        im.save(out,format='JPEG',quality=85)
        result.append({'number':number,'data':base64.b64encode(out.getvalue()).decode('ascii')})
    print(json.dumps({'pages':result}))
