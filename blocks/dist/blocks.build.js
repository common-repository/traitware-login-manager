!function(e){function t(n){if(r[n])return r[n].exports;var o=r[n]={i:n,l:!1,exports:{}};return e[n].call(o.exports,o,o.exports,t),o.l=!0,o.exports}var r={};t.m=e,t.c=r,t.d=function(e,r,n){t.o(e,r)||Object.defineProperty(e,r,{configurable:!1,enumerable:!0,get:n})},t.n=function(e){var r=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(r,"a",r),r},t.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},t.p="",t(t.s=0)}([function(e,t,r){"use strict";Object.defineProperty(t,"__esModule",{value:!0});r(1),r(4)},function(e,t,r){"use strict";var n=r(2),o=(r.n(n),r(3)),l=(r.n(o),wp.i18n.__),a=wp.blocks.registerBlockType,c=wp.editor,i=c.InspectorControls,s=c.InnerBlocks,u=wp.components,m=u.PanelBody,p=u.CheckboxControl,h=u.BaseControl,w=wp.element.createElement,f=w("svg",{width:20,height:20},w("path",{d:"M16.6,5.4V2.6h-4.1v2.8H12V4.9C11.9,2.2,9.7,0,7,0C5.7,0,4.4,0.5,3.5,1.4C2.6,2.3,2,3.5,2,4.9v4.2H1.6 C0.7,9.1,0,9.8,0,10.6v7.8c0,0.9,0.7,1.6,1.6,1.7h10.9c0.9,0,1.6-0.7,1.6-1.6v-6.1h2.5V9.6h2.8V5.4H16.6z M9.4,9.1h-5 c0,0,0-0.1,0-0.1V5.3c0.1-1.4,1.1-2.6,2.5-2.6c0.7,0,1.3,0.3,1.8,0.7c0.4,0.5,0.7,1.1,0.7,1.9V9.1z M18,8.2h-2.8V11h-1.5V8.2H11 V6.8h2.8V4h1.5v2.8H18V8.2z"}));a("traitware/traitware-protected-content-block",{title:l("TraitWare Protected Content"),icon:f,category:"common",keywords:[l("traitware protected content"),l("traitware"),l("protected content")],attributes:{roles:{type:"string",default:""}},edit:function(e){var t=e.attributes,r=e.setAttributes,n=t.roles.length?JSON.parse(t.roles):[],o=[];for(var a in traitware_block_obj.roles){(function(e){if(!traitware_block_obj.roles.hasOwnProperty(e))return"continue";var t=n.includes(e);o.push(wp.element.createElement(p,{label:traitware_block_obj.roles[e],checked:t,onChange:function(t){var o=n.includes(e),l=n;t&&!o?l.push(e):!t&&o&&l.splice(n.indexOf(e),1),r({roles:JSON.stringify(l)})}}))})(a)}return[wp.element.createElement(i,null,wp.element.createElement(m,{title:l("Roles")},wp.element.createElement(h,{label:l("Select which roles are able to view the protected content:")},o))),wp.element.createElement("div",{className:e.className},l("Add Blocks to protect"),wp.element.createElement(s,null))]},save:function(e){return wp.element.createElement(s.Content,null)}})},function(e,t){},function(e,t){},function(e,t,r){"use strict";var n=r(5),o=(r.n(n),r(6)),l=(r.n(o),wp.i18n.__),a=wp.blocks.registerBlockType,c=wp.editor.InspectorControls,i=wp.components,s=i.PanelBody,u=i.SelectControl,m=wp.element.createElement,p=m("svg",{width:20,height:20},m("path",{d:"M16.6,2.6V0h-4v1.2H0.3v18.6h14.8V9h1.5V6.5h2.7V2.6H16.6z M12.3,14h-9v-1h9V14z M12.3,12h-9v-1h9V12z M12.3,10h-9V9h9V10z M12.3,8h-9V7h9V8z M18,5.2h-2.7v2.6h-1.5V5.2h-2.7V3.9h2.7V1.3h1.5v2.6H18V5.2z"}));a("traitware/traitware-form-block",{title:l("TraitWare Form"),icon:p,category:"common",keywords:[l("traitware form"),l("traitware"),l("form")],edit:function(e){var t=e.attributes,r=e.setAttributes,n=[];for(var o in traitware_block_obj.forms)traitware_block_obj.forms.hasOwnProperty(o)&&n.push({value:traitware_block_obj.forms[o].id,label:traitware_block_obj.forms[o].title});return[wp.element.createElement(c,null,wp.element.createElement(s,{title:l("Form")},n.length>0?wp.element.createElement(u,{label:l("Select the form to use:"),value:t.form,onChange:function(e){r({form:e})},options:n}):wp.element.createElement("p",null,"No forms exist"))),wp.element.createElement("div",{className:e.className},l("TraitWare Form"))]},save:function(e){return wp.element.createElement("div",{className:e.className},l("TraitWare Form"))}})},function(e,t){},function(e,t){}]);