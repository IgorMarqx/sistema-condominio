# Fixtures de faturas

Os arquivos `*-sintetica.txt` imitam a saída do `pdftotext -layout` e **foram montados à mão**,
sem PDF real. Servem para travar o comportamento dos extratores, não para provar que eles leem
o layout verdadeiro da Energisa e da Cagepa.

Próximo passo (documento de implementação, seção 13): separar 3 a 6 PDFs reais (Energisa e
Cagepa, condomínio e individual), anonimizar nome/CPF/endereço e salvar aqui como
`energisa-<mes>.txt` (o texto do `pdftotext -layout`) junto do esperado no teste. Mudança de
layout quebra o teste, não a produção.
