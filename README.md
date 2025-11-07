Meu Jardim Virtual
--------------------------------------------------------------

Desenvolvido por: Emanuelle de Avila - RA 2509814

Sistema web para gerenciamento de plantas domésticas, permitindo cadastro, registro de cuidados e lembretes automáticos.


Funcionalidades
--------------------------------------------------------------


Cadastro de Plantas: nome, espécie, data de aquisição, localização, frequência de rega e notas.

Registro de Cuidados: tipo de cuidado, data, observações e próxima manutenção automática.

Lembretes: configuração de frequência por tipo de cuidado.

Dashboard: visão geral de cuidados do dia, próximos cuidados e estatísticas.

Lista de Plantas: tabela com informações e ações.

Autenticação: login e registro de usuários.

Calendário de cuidados.


Tecnologias
--------------------------------------------------------------

Backend: PHP puro

Banco de Dados: MySQL

Frontend: HTML5, CSS3, JavaScript, Tailwind


Instalação
--------------------------------------------------------------

Pré-requisitos

Servidor web (Apache/XAMPP/WAMP)

PHP 8+

MySQL 5.7+

Passos

Baixe o projeto e renomeie como "meu_jardim_virtual"


Coloque a pasta no diretório do servidor:

XAMPP: C:\xampp\htdocs\

WAMP: C:\wamp\www\

Linux: /var/www/html/

Crie o banco de dados sistema_plantas e execute database/estrutura.sql.

Configure config.php com suas credenciais:

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_plantas');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');


Acesse no navegador: http://localhost/meu_jardim_virtual e registre um usuário.

