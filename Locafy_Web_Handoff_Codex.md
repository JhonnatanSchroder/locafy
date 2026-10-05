# Locafy Web — Handoff completo para novo agente Codex

> Documento de continuidade do projeto **Locafy Web**. Leia antes de alterar o código.
>
> Objetivo: permitir que um novo agente continue o projeto sem perder contexto arquitetural, regras de negócio, padrões de código, estado atual e roadmap.

---

# 1. Visão geral do produto

**Locafy** é um sistema de gestão de locações, inicialmente focado em:

- andaimes;
- rodinhas de andaime;
- tábuas;
- betoneiras;
- clientes;
- contratos;
- retiradas/devoluções;
- fretes;
- cobranças;
- pagamentos;
- manutenção de equipamentos.

O projeto deve nascer preparado para futuramente funcionar como um **SaaS multiempresa**, sem transformar o código em uma arquitetura “enterprise” desnecessária.

A regra principal é: **Laravel é a fonte de verdade das regras de negócio**.

O frontend web, o futuro aplicativo React Native e futuras integrações via n8n/WhatsApp devem apenas consumir os casos de uso expostos pelo backend.

---

# 2. Stack atual do Locafy Web

Projeto atual:

- Laravel 13
- PHP
- Inertia.js 3
- Vue 3
- TypeScript
- Tailwind CSS 4
- shadcn-vue
- Wayfinder
- Pest
- Vite

Convenções importantes:

- páginas Inertia em `resources/js/pages/...`;
- nomes de domínio/backend preferencialmente em inglês;
- textos da interface em português;
- rotas frontend devem preferir helpers do Wayfinder em vez de URLs hardcoded.

Quando novas rotas/controllers forem criados e utilizados no frontend, atualizar Wayfinder com:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

---

# 3. Forma de trabalho esperada do agente

O objetivo do usuário também é **aprender programação enquanto constrói o sistema**.

Portanto, não agir apenas como gerador de código.

## Antes de implementar uma etapa significativa

1. Inspecionar o estado real do projeto.
2. Explicar o que será feito.
3. Listar os arquivos que serão criados/alterados.
4. Explicar decisões arquiteturais importantes.
5. Apresentar um plano pequeno.
6. **Aguardar aprovação antes de implementar**, quando o usuário estiver iniciando um novo módulo/etapa.

## Durante a implementação

- Implementar apenas a etapa aprovada.
- Não antecipar módulos futuros.
- Não refatorar código funcional que não faz parte da tarefa.
- Não instalar bibliotecas sem necessidade clara.
- Evitar abstrações prematuras.

## Depois da implementação

Explicar:

- arquivos criados;
- arquivos alterados;
- principais conceitos usados;
- como testar manualmente;
- possíveis efeitos colaterais.

Executar as validações relevantes.

Backend:

```bash
php artisan test --compact
vendor/bin/pint --format agent
```

Frontend:

```bash
npm run check
npm run types:check
npm run build
```

Quando `npm run check` indicar apenas formatação, pode usar:

```bash
npm run check:fix
npm run check
```

Não alterar testes apenas para “fazê-los passar” se o problema for uma regressão real.

---

# 4. Princípios arquiteturais

A arquitetura deve continuar simples e evoluir conforme a complexidade real aparecer.

## CRUD simples

Preferir:

```text
Route
→ FormRequest
→ Controller
→ Policy
→ Model
```

## Quando surgirem casos de uso de domínio

Usar **Actions** para operações como:

- RegisterWithdrawal;
- RegisterReturn;
- RegisterPayment;
- FinalizeContract;
- CreateCharge.

Use **Services** para regras reutilizáveis/cálculos, por exemplo:

- cálculo de dias cobrados;
- cálculo de valores de locação;
- regras de período de betoneira;
- regras financeiras reutilizadas por mais de um caso de uso.

## Responsabilidades

### Controller

Coordena HTTP/Inertia/API.

Não deve concentrar regra de negócio complexa.

### FormRequest

Valida entrada do usuário.

### Policy

Autoriza quem pode fazer o quê.

### Action

Representa um caso de uso de domínio.

### Service

Representa uma regra/cálculo reutilizável.

### Model

Dados, casts, relacionamentos e comportamento simples do próprio modelo.

---

# 5. Multiempresa — regra obrigatória

O Locafy é preparado para SaaS multiempresa desde a fundação.

Existe uma entidade `Company`.

Cada `User` pertence a uma empresa através de `company_id`.

Atualmente `users.company_id` permanece nullable por segurança de migração/desenvolvimento.

## Regras de isolamento

Para entidades de negócio:

- usuário só acessa dados da própria empresa;
- usuário sem empresa recebe `403` nos módulos protegidos;
- registro pertencente a outra empresa deve normalmente aparecer como `404` em páginas escopadas;
- nunca confiar em `company_id` enviado pelo frontend;
- preferir criação via relacionamento da empresa autenticada;
- `company_id` não deve ficar no `$fillable` de modelos de negócio.

Exemplo de criação:

```php
$request->user()
    ->company
    ->clients()
    ->create($request->validated());
```

Exemplo de consulta:

```php
Model::query()
    ->whereBelongsTo($company)
    ->findOrFail($id);
```

Não criar trait/global scope de multi-tenancy sem uma necessidade real e aprovação explícita.

---

# 6. Usuários e papéis

Existe enum `UserRole` preparado para o futuro.

Papéis planejados/definidos:

- ADMIN
- ATTENDANT / ATENDENTE
- FINANCIAL / FINANCEIRO
- DELIVERY / ENTREGADOR

No momento, permissões finas por role **ainda não são o foco**.

Até agora as Policies usam principalmente isolamento por empresa.

Não implementar RBAC completo sem uma tarefa específica.

---

# 7. Módulos já implementados

## 7.1 Foundation — Company/User

Já existe a base multiempresa.

Principais pontos:

- `Company` model;
- relacionamento Company/User;
- `users.company_id`;
- `UserRole`;
- factory/seeder de desenvolvimento;
- testes de relacionamento/isolamento da fundação.

Não refazer essa fundação sem motivo.

---

# 8. Clientes — IMPLEMENTADO

Entidade `Client`.

## Campos atuais

- type
- name
- document nullable
- phone nullable
- residential_address nullable
- notes nullable
- company_id
- timestamps

Somente `name` é obrigatório na visão de negócio inicial.

Tipos:

```text
INDIVIDUAL
COMPANY
```

## Regras implementadas

- `Client belongsTo Company`;
- `Company hasMany Clients`;
- `company_id` fora do fillable;
- CRUD sem exclusão definitiva;
- multiempresa;
- busca por nome/telefone/documento;
- registro de outra empresa retorna `404`;
- usuário sem empresa recebe `403`;
- criação via relacionamento da empresa.

Frontend existente:

```text
resources/js/pages/clients/
├── Index.vue
├── Create.vue
├── Show.vue
└── Edit.vue
```

Não alterar regras desse módulo em tarefas puramente visuais.

---

# 9. Produtos — IMPLEMENTADO

Entidade `Product` representa o **tipo/categoria comercial** de algo locável.

Exemplos:

- Andaime
- Rodinha
- Tábua
- Betoneira

## ProductType

```text
QUANTITY
INDIVIDUAL
```

### QUANTITY

Controlado apenas por quantidade.

Exemplos:

- Andaime
- Rodinha
- Tábua

### INDIVIDUAL

Cada unidade física será cadastrada separadamente em `Equipment`.

Exemplo:

```text
Product: Betoneira

Equipment:
- Betoneira 01
- Betoneira 02
- Betoneira 03
```

## Campos atuais de Product

- company_id
- name
- type
- default_price nullable
- unit nullable
- stock_total nullable
- active
- timestamps

## Regras atuais

- `QUANTITY` exige `stock_total` >= 0;
- `INDIVIDUAL` sempre normaliza `stock_total = null`;
- `company_id` fora do fillable;
- Product pertence à Company;
- Company possui Products;
- CRUD sem destroy;
- busca/filtros;
- multiempresa;
- `available` ainda é apenas:
  - QUANTITY → `stock_total`;
  - INDIVIDUAL → `null`.

Ainda não existe cálculo real de estoque alugado.

Isso virá quando contratos/movimentações existirem.

## Seeder de desenvolvimento

Existe catálogo inicial da empresa demo com:

- Andaime — QUANTITY — preço padrão 0,60;
- Rodinha — QUANTITY — preço padrão 1,00;
- Tábua — QUANTITY — preço padrão 1,00;
- Betoneira — INDIVIDUAL.

Observação arquitetural importante:

No futuro, evitar seeders que sobrescrevam preços/configurações reais de produção. Para produção, preferir estratégias que não substituam alterações feitas pelo usuário.

Frontend:

```text
resources/js/pages/products/
├── Index.vue
├── Create.vue
├── Show.vue
└── Edit.vue
```

---

# 10. Equipamentos Individuais — IMPLEMENTADO

Último módulo de domínio concluído antes do handoff.

Entidade `Equipment` representa uma unidade física identificável de um `Product` do tipo `INDIVIDUAL`.

Exemplo:

```text
Product: Betoneira

Equipments:
- Betoneira 01
- Betoneira 02
```

## EquipmentStatus

```text
AVAILABLE
RENTED
MAINTENANCE
INACTIVE
```

Labels atuais de UI:

```text
AVAILABLE    → Disponível
RENTED       → Alugado
MAINTENANCE  → Manutenção
INACTIVE     → Inativo
```

## Campos atuais

- company_id
- product_id
- name
- brand nullable
- notes nullable
- status
- timestamps

## Relacionamentos

```text
Company hasMany Equipments
Product hasMany Equipments
Equipment belongsTo Company
Equipment belongsTo Product
```

## Regra principal

Um Equipment só pode usar um Product:

```text
mesma Company
+
ProductType::INDIVIDUAL
```

A validação é backend, não apenas UI.

Usa `Rule::exists(...)->where(...)` com empresa + tipo.

## Produto ativo/inativo

Create:

- dropdown mostra apenas Product INDIVIDUAL ativo da empresa.

Edit:

- mostra Products INDIVIDUAL ativos da empresa;
- **mais o Product atual do Equipment mesmo se estiver inativo**.

Isso permite editar um equipamento antigo sem obrigar a mudar seu Product.

## Factory

A factory deve preservar a invariável:

```text
Equipment.company_id = Equipment.product.company_id
```

Nos testes multiempresa, preferir criação explícita do Product e Equipment com a mesma empresa.

## CRUD

Existe:

- index
- create
- store
- show
- edit
- update

Sem destroy.

Status ainda é manual.

A automação futura:

```text
AVAILABLE → RENTED
RENTED → AVAILABLE
```

só deverá aparecer quando Contratos/Movimentações forem implementados.

Frontend:

```text
resources/js/pages/equipments/
├── Index.vue
├── Create.vue
├── Show.vue
└── Edit.vue
```

Testes do módulo já foram implementados para isolamento, validação, dropdown, product type, company, status e produto inativo em edição.

---

# 11. Estado atual dos testes

Após Equipamentos, a última execução reportada foi:

```text
71 passed
3 skipped
301 assertions
```

Além disso passaram:

- Pint;
- frontend check/lint;
- TypeScript type-check;
- Vite build.

O novo agente deve **revalidar o estado atual do repositório** antes de assumir que esses números ainda são idênticos.

---

# 12. Identidade visual do Locafy — DECISÃO APROVADA

O aplicativo React Native do Locafy já definiu uma identidade visual aprovada.

O Web deve usar a **mesma identidade**, mas não copiar layout mobile para desktop.

Objetivo:

```text
Mesma marca / mesmas cores / mesma linguagem visual
+
UX apropriada para desktop
```

## Nome correto

```text
Locafy
```

Pode usar `LOCAFY` visualmente no branding.

Não usar `LOCIFY`.

---

# 13. Paleta visual aprovada

Referência aproximada:

```text
Primary             #2563EB
Dark background     #020617
Dark card           #0F172A
Dark secondary      #111C30
Border              #1E293B
Foreground          #F8FAFC
Muted foreground    #94A3B8
Success             #10B981
Warning             #F59E0B
Danger              #EF4444
```

Esses valores são referência para **tokens globais**, não para hardcode por página.

---

# 14. Tailwind/shadcn atual

O projeto usa **Tailwind CSS v4 CSS-first**.

A identidade deve ser centralizada principalmente em:

```text
resources/css/app.css
```

O projeto usa tokens shadcn/CSS variables como:

```text
background
foreground
card
card-foreground
popover
popover-foreground
primary
primary-foreground
secondary
secondary-foreground
muted
muted-foreground
accent
accent-foreground
destructive
border
input
ring
sidebar-background
sidebar-foreground
sidebar-primary
sidebar-accent
sidebar-border
sidebar-ring
```

Adicionar, se ainda não tiver sido implementado:

```text
success
success-foreground
warning
warning-foreground
```

A prioridade visual aprovada é:

```text
Design tokens
↓
componentes base
↓
layout/sidebar
↓
páginas existentes
```

Evitar:

```text
bg-[#020617]
text-[#94A3B8]
```

espalhados pelas páginas.

---

# 15. UI Web — tarefa atual PENDENTE no momento do handoff

O agente anterior **analisou** essa etapa, mas no último estado compartilhado ainda estava aguardando aprovação/implementação visual.

O novo agente deve primeiro verificar se essas mudanças já foram aplicadas no repositório antes de fazer qualquer coisa.

## Objetivo

Alinhar o Locafy Web à identidade já aprovada do app mobile.

## Dark mode

Deve ficar especialmente próximo do aplicativo:

- navy quase preto;
- cards azul/cinza escuro;
- azul Locafy;
- texto branco suave;
- muted azulado;
- borders discretas;
- evitar preto puro em todas as superfícies.

## Light mode

Continuar suportando:

```text
light
dark
system
```

Light deve ter:

- background frio/quase branco;
- cards brancos;
- foreground navy;
- primary azul Locafy;
- muted levemente azulado;
- borders claras.

## Sidebar

Manter estrutura desktop.

Conteúdo atual:

```text
LOCAFY

Dashboard
Clientes
Produtos
Equipamentos
```

Refinamentos desejados:

- fundo navy no dark;
- item ativo com destaque azul;
- hover discreto;
- ícones consistentes;
- remover label genérico `Platform` se deixar mais limpo;
- não alterar rotas/navegação/Wayfinder.

## Componentes base

Primeiro tentar resolver visual pelos tokens.

Só alterar internals de componentes shadcn quando tokens não forem suficientes.

Componentes relevantes:

- Button
- Card
- Input

Evitar quebrar páginas de auth/configurações/perfil.

## Badge

É aprovado criar um componente visual `Badge` reutilizável com variantes:

```text
default
success
warning
danger
muted
info
```

Badge não deve conter regra de negócio.

Páginas escolhem a variante.

### Produtos

```text
Ativo   → success
Inativo → muted
```

### Equipamentos

```text
Disponível  → success
Alugado     → info
Manutenção  → warning
Inativo     → muted
```

## Dashboard

O Dashboard atual era basicamente placeholder do Starter Kit.

Objetivo visual:

- remover aparência genérica;
- não inventar métricas;
- não espalhar “Em breve” por vários cards;
- pode usar estado vazio elegante ou `—` enquanto dados ainda não existem.

Preparar visualmente o espaço futuro para:

- Contratos ativos;
- Cobranças;
- Financeiro;
- Equipamentos.

Mas sem simular dados backend.

## Clientes / Produtos / Equipamentos

Aplicar apenas refinamento visual:

- cabeçalho consistente;
- título forte;
- descrição muted;
- ação primária à direita no desktop;
- tabela moderna;
- header de tabela discreto;
- hover;
- borders leves;
- filtros;
- badges;
- formulários coerentes.

Não transformar tabelas desktop em cards mobile.

Manter responsividade com `overflow-x-auto` e filtros quebrando linha quando necessário.

## Formulários

Nesta etapa, não fazer grande refatoração.

Pode manter classes repetidas e aproveitar os novos tokens.

Extrair novos componentes somente se a repetição justificar futuramente.

## Escopo dessa tarefa visual

Não alterar:

- Models;
- migrations;
- Controllers;
- FormRequests;
- Policies;
- factories;
- regras multiempresa;
- cálculos;
- rotas backend;
- Wayfinder;
- lógica de negócio.

Não implementar nessa tarefa:

- Manutenções;
- Contratos;
- Movimentações;
- Cobranças;
- Pagamentos;
- API.

---

# 16. Próximo módulo depois da UI: Manutenções

Depois de concluir a identidade visual, o próximo módulo planejado é **Manutenções de Equipment**.

Ainda não implementar automaticamente.

Primeiro apresentar plano e aguardar aprovação.

Conceito geral planejado:

```text
Equipment hasMany Maintenances
Maintenance belongsTo Equipment
```

Necessidades conhecidas:

- data;
- descrição;
- valor/custo;
- observação;
- histórico por equipamento.

A empresa quer futuramente saber:

- histórico de manutenção da betoneira;
- custo de manutenção por equipamento;
- custo total de manutenção;
- receita por betoneira;
- comparação receita x custo.

Status `MAINTENANCE` já existe em Equipment.

Não criar fluxo automático de status sem discutir antes.

---

# 17. Contratos — REGRAS PLANEJADAS, AINDA NÃO IMPLEMENTADAS

Não implementar sem etapa específica.

## Status planejados

```text
ATIVO
DEVOLVIDO
FINALIZADO
CANCELADO
```

### ATIVO

Enquanto houver equipamento/material fora.

### DEVOLVIDO

Quando todos os itens tiverem sido devolvidos.

### FINALIZADO

Somente após:

- tudo devolvido;
- saldo financeiro zerado;
- ação explícita do usuário em “Finalizar”.

Não auto-finalizar automaticamente.

### CANCELADO

Para fluxo futuro.

---

# 18. Contract e ContractItem — visão futura

Um contrato pode conter múltiplos produtos ao mesmo tempo.

Exemplo:

```text
Contrato #102

20x Andaime
4x Rodinha
2x Tábua
1x Betoneira 02
```

Para QUANTITY:

- Product;
- quantidade;
- preço copiado para ContractItem.

Para INDIVIDUAL:

- Product;
- Equipment físico específico.

Preço padrão do Product é apenas um default.

Ao criar o contrato, o preço deve ser copiado para o item.

Alterações futuras no `Product.default_price` não podem alterar contratos já existentes.

Se o preço do item for alterado manualmente durante um contrato existente, a decisão atual é **retroativa para aquele item/contrato inteiro**, com auditoria futura.

---

# 19. Movimentações — visão futura

Precisamos suportar:

- múltiplas retiradas;
- devoluções parciais;
- várias datas diferentes no mesmo contrato.

Cada movimentação deve armazenar **data e hora real do ocorrido**.

Deve permitir lançamento retroativo.

É importante distinguir:

```text
ocorrido_em
created_at
```

Uma movimentação poderá conter vários itens.

Movimentos de retirada podem gerar frete.

O usuário escolheu permitir edição de movimentações, mas alterações importantes deverão ser auditadas futuramente.

---

# 20. Regras de cobrança por tempo — Andaimes/Rodinhas/Tábuas

Esses produtos usam regras de diária.

Preços padrão atuais:

```text
Andaime  → R$ 0,60 por peça/dia
Rodinha  → R$ 1,00 por unidade/dia
Tábua    → R$ 1,00 por unidade/dia
```

Valores devem ser editáveis no contrato.

## Domingos

Domingo **não é cobrado** para esses itens.

## Sábado

Sábado é cobrado por padrão.

Cada contrato deve poder definir se sábado será cobrado ou não.

Essa configuração se aplica aos itens de diária do contrato.

## Regra das 10:00

Retirada:

```text
antes das 10:00
→ dia atual não conta

10:00 ou depois
→ dia atual conta
```

Devolução:

```text
antes das 10:00
→ dia da devolução não é cobrado

10:00 ou depois
→ dia da devolução é cobrado
```

Cada lote retirado em uma data/hora diferente acumula dias de forma independente.

Uma devolução parcial interrompe a cobrança apenas da quantidade devolvida.

Essa regra deve futuramente morar no domínio Laravel, provavelmente em Service + Actions, com testes automatizados detalhados.

Não colocar esses cálculos no Vue ou no app mobile.

---

# 21. Betoneiras — regra futura de cobrança

Betoneiras são `ProductType::INDIVIDUAL` e cada máquina é `Equipment`.

Planos/preços padrão conhecidos:

```text
Diária   → R$ 120
Semanal  → R$ 250
Mensal   → R$ 500
```

Esses preços poderão ser editados por contrato.

Regras diferentes dos andaimes:

- sábado conta;
- domingo conta;
- não usa corte das 10:00.

Exemplo discutido:

```text
alugada segunda no plano semanal
encerrada segunda seguinte
→ continua sendo uma semana pelo valor semanal
```

Estratégia exata de excedentes deve ficar isolada em Service para poder evoluir.

Não implementar regra definitiva sem discutir novamente.

---

# 22. Fretes — visão futura

Frete será uma entidade separada, apesar de integrado ao fluxo de contrato/movimentação.

Tipos planejados:

```text
DELIVERY / ENTREGA
ADDITIONAL_DELIVERY / ENTREGA_ADICIONAL
PICKUP / BUSCA
OTHER / OUTRO
```

Valor padrão atual da empresa:

```text
R$ 15
```

Mas pode ser editado por ocorrência, inclusive `0`.

Frete entra no valor devido imediatamente.

---

# 23. Cobranças — visão futura

Deve existir uma página dedicada de Cobranças.

Conceito:

Uma `Charge` representa um **snapshot do valor devido naquele momento**.

Status planejados:

```text
PENDING
PARTIAL
PAID
CANCELLED
```

Filtros/tabs planejados:

```text
Hoje
Atrasadas
Próximas
Todas
```

Uma cobrança pode ter observação, por exemplo promessa de pagamento do cliente.

Intervalo padrão conhecido:

```text
15 dias
```

Quando uma cobrança é totalmente paga, a próxima cobrança normalmente deve ser sugerida para 15 dias após o pagamento/data corrente, com campo editável.

Se pagamento for parcial, a cobrança continua aberta até o restante ser quitado/descontado.

Não criar regra financeira fora do backend Laravel.

---

# 24. Pagamentos / descontos / créditos — visão futura

Métodos planejados:

```text
PIX
Dinheiro
Cartão
Transferência
Outro
```

Suportar pagamento parcial.

Suportar desconto.

Exemplo:

```text
Cobrança: R$ 400
Pagamento: R$ 350
Desconto: R$ 50
→ cobrança quitada
```

Um pagamento não deve ser espalhado automaticamente por várias cobranças.

Pagamento antecipado sem cobrança é permitido.

Esse valor vira **crédito do contrato** e futuramente deve ser aplicado automaticamente a novas cobranças.

O resumo principal do contrato deve mostrar, de forma simples:

- valor acumulado até hoje;
- pagamentos;
- descontos;
- créditos;
- restante.

Evitar poluir a tela principal com excesso de conceitos como “valor faturado x não faturado” se não forem necessários ao usuário.

---

# 25. Dashboard futuro

Indicadores desejados:

- contratos ativos;
- devolvidos aguardando pagamento;
- cobranças de hoje;
- cobranças atrasadas;
- recebido hoje;
- recebido no mês;
- equipamentos disponíveis/alugados;
- alertas operacionais;
- gráficos futuros.

Não inventar métricas antes de o backend fornecer os dados.

---

# 26. Auditoria futura

Desejada para ações importantes.

Idealmente registrar:

- usuário;
- ação;
- data/hora;
- entidade;
- id;
- antes;
- depois.

Objetivos:

- histórico dentro do contrato;
- página global de atividades.

Registros importantes não devem ser apagados silenciosamente.

Preferir conceitos como:

- cancelar;
- anular;
- reverter;
- desativar.

---

# 27. Anexos / fotos / PDF / mapas — futuro

Planejado:

- fotos em contratos/obras/equipamentos;
- legenda;
- usuário que enviou;
- data/hora;
- arquitetura polimórfica de attachments;
- contrato PDF;
- logo da empresa;
- dados cliente;
- endereço obra;
- itens;
- preços;
- termos específicos da empresa;
- assinaturas;
- assinatura touch futura;
- Google Maps futuramente.

Não implementar sem etapa específica.

---

# 28. Relatórios — futuro

Relatórios desejados:

- receita por período;
- pagamentos;
- cobranças pendentes;
- contratos ativos/finalizados;
- contas a receber/devedores;
- equipamentos alugados;
- estoque;
- receita por betoneira;
- manutenção por betoneira;
- custos de manutenção.

Filtros:

- hoje;
- semana;
- mês;
- período personalizado.

Exportações futuras:

- PDF;
- Excel.

---

# 29. Financeiro completo — futuro

Posteriormente o sistema poderá incluir:

- despesas;
- combustível;
- salários;
- compras;
- manutenção;
- caixa diário;
- recebimentos separados por forma de pagamento.

Custo de manutenção deve futuramente entrar também como despesa geral.

---

# 30. API, n8n, WhatsApp e IA — futuro

Não implementar agora sem tarefa explícita.

Arquitetura pretendida:

```text
WhatsApp
↓
WAHA
↓
n8n
↓
LLM interpreta intenção
↓
Laravel API
↓
Actions/Services
↓
Banco
```

Regra fundamental:

**IA não deve calcular dinheiro nem acessar banco diretamente.**

Laravel valida e calcula tudo.

Ações sensíveis, como registrar pagamento, deverão ter confirmação.

Consultas podem ser executadas sem confirmação.

API futura provável:

```text
/api/v1
```

A mesma camada de domínio deve servir:

- Web/Inertia;
- API/n8n;
- app React Native.

Não duplicar regra de negócio em Controllers web e API.

---

# 31. App mobile React Native — contexto para arquitetura do Web

Existe um projeto separado `locafy-mobile`.

Ele não fica dentro do Locafy Web.

Stack mobile:

- Expo
- React Native
- TypeScript
- Expo Router
- NativeWind
- Lucide React Native

O app está inicialmente usando mocks para validar UX.

O backend Laravel continuará sendo a autoridade do domínio.

O Web deve ser preparado para expor futuramente a mesma lógica via API, mas **não criar API prematuramente**.

---

# 32. Roadmap principal do Web

Estado aproximado:

```text
✅ Foundation Company/User
✅ Clientes
✅ Produtos
✅ Equipamentos Individuais
➡️ Identidade visual Locafy Web
⬜ Manutenções
⬜ Contratos
⬜ Itens do contrato
⬜ Retiradas / Devoluções
⬜ Fretes
⬜ Motor de cálculo
⬜ Cobranças
⬜ Pagamentos / descontos / créditos
⬜ Dashboard real
⬜ Auditoria
⬜ Anexos
⬜ PDF
⬜ Relatórios
⬜ Permissões por role
⬜ API v1
⬜ n8n / WhatsApp / IA
```

Não avançar vários módulos em uma única entrega.

---

# 33. Primeira tarefa do novo agente Codex

Ao assumir o projeto:

## Passo 1

Leia este documento.

## Passo 2

Inspecione o repositório real.

Confirme:

- versões;
- módulos existentes;
- migrations;
- Models;
- Requests;
- Policies;
- Controllers;
- rotas;
- páginas Vue;
- testes;
- estado atual do CSS/tema.

## Passo 3

Compare o repositório com este handoff.

Se houver divergência, **o repositório atual é a fonte de verdade sobre o que já está implementado**, mas não mude regras de negócio sem discutir.

## Passo 4

Verifique se a etapa de identidade visual já foi implementada.

Se NÃO foi implementada:

- apresente um plano curto para implementar a identidade Locafy descrita nas seções 12–15;
- não altere arquivos antes da aprovação.

Se JÁ foi implementada:

- explique o estado encontrado;
- não refaça o tema;
- prepare o plano para o próximo módulo: **Manutenções**.

---

# 34. Restrições importantes para o novo agente

Não:

- refatorar tudo “porque existe uma maneira melhor”;
- trocar a stack;
- criar arquitetura enterprise sem necessidade;
- adicionar repository pattern por padrão;
- criar Services para CRUD simples;
- colocar regra financeira no Vue;
- colocar cálculo de domínio em n8n/mobile;
- criar API antes da fase planejada;
- criar novos módulos sem aprovação;
- quebrar isolamento multiempresa;
- confiar em company_id do request;
- criar hard delete de entidades importantes;
- inventar métricas/dados reais no Dashboard;
- misturar o projeto React Native com o Laravel Web.

---

# 35. Objetivo de qualidade

O Locafy deve continuar simples o suficiente para o usuário entender o código, mas sólido o suficiente para crescer.

Sempre privilegiar:

```text
clareza
+
consistência
+
regras testadas
+
isolamento multiempresa
+
boa UX
```

em vez de quantidade de abstrações.

