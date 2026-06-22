# 🚌 Calendário de Recarga do Transporte Coletivo

**Plugin WordPress** para gerenciar e exibir calendários de recarga do transporte coletivo municipal, desenvolvido para a Intranet Corporativa Moderna.

## Funcionalidades

### 📆 Gerenciamento no Personalizador
Painel administrativo dentro do **Personalizador do WordPress** (`Aparência > Personalizar > Recarga do Transporte Coletivo`) com duas seções:

| Seção | Função |
|---|---|
| **Gerenciar Calendários** | Cadastrar anos e preencher, para cada mês, as datas de início e término da recarga |
| **Aparência** | Enviar logo (PNG) e ajustar a largura de exibição |

### 🏠 Exibição na Home
Bloco visual injetado automaticamente na página inicial, posicionado entre os widgets *Tempo em Três Corações* e *Aniversariantes do Dia*, no mesmo estilo e proporção.

O layout possui **3 colunas**:

```
┌──────────────────────────────────────────────────────────────┐
│  Recarga do Transporte Coletivo                                │
│  ┌──────────┬──────────────┬─────────────────────────────────┐│
│  │  LOGO    │  PRÓXIMA     │  📅 Próximas Recargas           ││
│  │  (22%)   │  RECARGA     │  • Junho: 22/06 a 25/06        ││
│  │          │  Junho       │  • Julho: 06/07 a 09/07        ││
│  │          │  Faltam 3d   │  [Ver calendário completo →]    ││
│  └──────────┴──────────────┴─────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

### Estados automáticos

O plugin detecta a data atual e exibe o estado correspondente:

- **✅ Recarga Aberta!** — quando a data atual está dentro de um período de recarga (mostra dias restantes para encerrar)
- **Próxima Recarga** — quando a próxima recarga ainda não começou (mostra mês e contagem regressiva para o início)
- **Indisponível** — quando não há calendários cadastrados ou todas as recargas já passaram

Se o calendário do ano atual terminou, ele procura automaticamente pelo próximo ano.

### 📄 Página completa
O botão **"Ver calendário completo"** abre uma página com todos os meses do ano, tabela com situação (andamento/futura/realizada) e navegação entre anos.

### 🎨 Design
- Degradê vermelho (`#ca1f26 → #e85d5d`) para combinar com a identidade visual
- Mesmo estilo, bordas arredondadas e sombras dos demais widgets da intranet
- Layout 100% responsivo (3 colunas no desktop, empilhado no mobile)
- Suporte a tema claro e escuro (`prefers-color-scheme`)

## Instalação

1. Copie a pasta `calendario-recarga-transporte` para `wp-content/plugins/`
2. Vá em **Plugins > Plugins Instalados** e ative **"Calendário de Recarga do Transporte Coletivo"**
3. Acesse **Aparência > Personalizar > Recarga do Transporte Coletivo**
4. Cadastre os anos e preencha as datas de recarga

## Uso

### Personalizador
No painel **Gerenciar Calendários**:
1. Clique em **"+ Novo Ano"** e digite o ano (ex: 2026)
2. Preencha as datas de **início** e **fim** para cada mês desejado
3. Clique em **"Publicar"** para salvar

Em **Aparência**:
1. Faça upload de uma logo (PNG recomendado)
2. Ajuste a largura (padrão: 80px)

### Widget
O plugin registra o widget **"Calendário de Recarga do Transporte"** que pode ser adicionado a qualquer sidebar.

### Shortcodes

| Shortcode | Descrição |
|---|---|
| `[calendario_recarga_widget]` | Exibe o widget em qualquer lugar do site |
| `[calendario_recarga_completo]` | Exibe a tabela completa do ano atual |
| `[calendario_recarga_completo year="2027"]` | Exibe a tabela de um ano específico |

### Página dedicada
Acesse `?crtc_calendario=1` no site para ver o calendário completo com navegação entre anos.

## Estrutura de dados

Os calendários são armazenados na tabela `wp_options` sob a chave `calendario_recarga_data` no formato JSON:

```json
{
  "2026": [
    { "mes": 1, "inicio": "2026-01-06", "fim": "2026-01-09" },
    { "mes": 2, "inicio": "2026-02-02", "fim": "2026-02-05" }
  ],
  "2027": [
    { "mes": 1, "inicio": "2027-01-05", "fim": "2027-01-08" }
  ]
}
```

A logo é armazenada na opção `crtc_logo` (URL da imagem) e a largura em `crtc_logo_width`.

## Requisitos

- WordPress 5.0+
- PHP 7.0+
- Tema compatível (otimizado para o tema Intranet Corporativa Moderna)

## Arquivos do plugin

```
calendario-recarga-transporte/
├── calendario-recarga-transporte.php   — Plugin principal
├── includes/
│   └── class-crtc-control.php          — Controle do Personalizador
├── assets/
│   └── css/
│       └── frontend.css                — Estilos do widget e seção home
└── README.md                           — Este arquivo
```

## Compatibilidade

O plugin funciona em qualquer tema WordPress, mas a **injeção automática na Home** foi desenhada para o tema **Intranet Corporativa Moderna**. Em outros temas, utilize o widget ou os shortcodes para posicionar o conteúdo manualmente.
