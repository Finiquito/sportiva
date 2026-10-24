def lum(h):
    h=h.lstrip('#');r,g,b=[int(h[i:i+2],16)/255 for i in (0,2,4)]
    f=lambda v:v/12.92 if v<=.03928 else ((v+.055)/1.055)**2.4
    return .2126*f(r)+.7152*f(g)+.0722*f(b)
def cr(a,b):
    la,lb=lum(a),lum(b);return (max(la,lb)+.05)/(min(la,lb)+.05)
if __name__=='__main__':
    P={'verde':'#0f4f4e','hueso':'#f7f1e8','naranja':'#fc5603','dorado':'#bd984a','lila':'#bd8dff','exito':'#00ab05','amarillo':'#f6f24a','blanco':'#ffffff','tinta':'#10201f','tinta2':'#4b5e5c'}
    for a,b in [('verde','hueso'),('verde','blanco'),('blanco','verde'),('tinta','hueso'),('tinta2','hueso'),('tinta2','blanco'),('blanco','naranja'),('tinta','naranja'),('verde','naranja'),('naranja','hueso'),('naranja','verde'),('tinta','dorado'),('dorado','hueso'),('dorado','verde'),('tinta','lila'),('verde','lila'),('lila','verde'),('lila','hueso'),('blanco','exito'),('tinta','exito'),('exito','hueso'),('exito','verde'),('tinta','amarillo'),('verde','amarillo'),('amarillo','verde'),('hueso','verde')]:
        print(a,'/',b,round(cr(P[a],P[b]),2))
    for h in ['#b93c00','#c24100','#8a5f00','#7a5a1a','#008a04','#006b03','#7a4fd6','#6a3fc4']:
        print(h,'sobre hueso',round(cr(h,'#f7f1e8'),2),'sobre blanco',round(cr(h,'#ffffff'),2))
