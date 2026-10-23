import sys
from PIL import Image
# uso: sheet.py out.png suf ids... (suf m|d)
out,suf,*ids=sys.argv[1:]
ims=[Image.open('/tmp/w/all/%s-%s.png'%(i,suf)) for i in ids]
maxh=1900 if suf=='m' else 1300
sc=min(1,maxh/max(i.height for i in ims)) if suf=='m' else min(1,1900/(sum(i.width for i in ims)+20*(len(ims)-1)))
ims=[i.resize((int(i.width*sc),int(i.height*sc))) for i in ims]
if suf=='d':
    W=max(i.width for i in ims);H=sum(i.height for i in ims)+20*(len(ims)-1)
    c=Image.new('RGB',(W,H),(218,216,208));y=0
    for i in ims:c.paste(i,(0,y));y+=i.height+20
else:
    W=sum(i.width for i in ims)+20*(len(ims)-1);H=max(i.height for i in ims)
    c=Image.new('RGB',(W,H),(218,216,208));x=0
    for i in ims:c.paste(i,(x,0));x+=i.width+20
c.save(out);print(c.size,sc)
